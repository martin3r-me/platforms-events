<?php

namespace Platform\Events\Services;

use Platform\Events\Models\DocumentTemplate;
use Platform\Events\Models\EmailLog;
use Platform\Events\Models\Event;

/**
 * Versendet die erste Info-Mail nach Anlage einer Veranstaltung
 * (Lastenheft 2.3.2 „Automatisierte Versandfunktion").
 *
 * Architektur — Loose Coupling zum CRM-Modul:
 *   - Echter Mail-Versand laeuft ueber Platform\Crm\Services\Comms\PostmarkEmailService.
 *   - Wir referenzieren die Klasse nur defensiv ueber class_exists, damit
 *     platforms-events ohne installiertes CRM-Modul lauffaehig bleibt.
 *   - Ist kein CRM/Comms-Channel da, wird die Mail nicht versendet, sondern
 *     nur als „queued"-Eintrag in events_email_log audited.
 *
 * Platzhalter im Template und im Betreff werden via ContractRenderer ersetzt.
 */
class EventInitialInfoMailer
{
    /**
     * @return array{status:string,message:string,email_log_id:?int}
     */
    public static function send(
        Event $event,
        string $to,
        ?int $templateId = null,
        ?int $channelId = null,
        ?string $subjectOverride = null,
    ): array {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid_recipient', 'message' => 'Empfänger-Adresse fehlt oder ungültig.', 'email_log_id' => null];
        }

        $teamId = (int) $event->team_id;

        // 1) Template laden — Default aus Settings, wenn nicht uebergeben.
        $templateId ??= SettingsService::initialInfoTemplateId($teamId);
        $template = $templateId
            ? DocumentTemplate::where('team_id', $teamId)->where('is_active', true)->find($templateId)
            : null;
        if (!$template) {
            return ['status' => 'no_template', 'message' => 'Keine aktive Dokumentvorlage für die Erstinfo gewählt.', 'email_log_id' => null];
        }

        // 2) Subject + Body rendern (Platzhalter durchziehen).
        $subjectTemplate = trim((string) ($subjectOverride ?? SettingsService::initialInfoSubject($teamId)));
        if ($subjectTemplate === '') {
            $subjectTemplate = SettingsService::INITIAL_INFO_DEFAULT_SUBJECT;
        }
        $subject = ContractRenderer::renderPlaceholders($subjectTemplate, $event);
        $htmlBody = ContractRenderer::renderPlaceholders((string) $template->html_content, $event);

        // 3) EmailLog vorab als „queued" anlegen — wir aktualisieren ihn nach dem Versand.
        $log = EmailLog::create([
            'team_id'  => $teamId,
            'user_id'  => $event->user_id,
            'event_id' => $event->id,
            'type'     => 'initial_info',
            'to'       => $to,
            'subject'  => $subject,
            'body'     => $htmlBody,
            'status'   => 'queued',
        ]);

        // 4) Versand-Channel ermitteln (defensiv — CRM kann fehlen).
        if (!class_exists(\Platform\Crm\Models\CommsChannel::class)
            || !class_exists(\Platform\Crm\Services\Comms\PostmarkEmailService::class)
        ) {
            $log->update(['status' => 'failed']);
            ActivityLogger::log($event, 'event', 'Erstinfo-Versand übersprungen: CRM-/Comms-Modul nicht verfügbar.');
            return ['status' => 'no_comms_module', 'message' => 'CRM/Comms-Modul nicht installiert — Mail nicht versendet.', 'email_log_id' => $log->id];
        }

        $channelId ??= SettingsService::initialInfoCommsChannelId($teamId);
        $channelQuery = \Platform\Crm\Models\CommsChannel::query()
            ->where('team_id', $teamId)
            ->where('type', 'email')
            ->where('is_active', true);
        $channel = $channelId
            ? $channelQuery->find($channelId)
            : $channelQuery->orderBy('id')->first();

        if (!$channel) {
            $log->update(['status' => 'failed']);
            ActivityLogger::log($event, 'event', 'Erstinfo-Versand fehlgeschlagen: kein Email-Channel im CRM gefunden.');
            return ['status' => 'no_channel', 'message' => 'Kein aktiver Email-Channel im CRM gefunden.', 'email_log_id' => $log->id];
        }

        // 5) Echter Versand.
        try {
            /** @var \Platform\Crm\Services\Comms\PostmarkEmailService $svc */
            $svc = app(\Platform\Crm\Services\Comms\PostmarkEmailService::class);
            $svc->send(
                $channel,
                $to,
                $subject,
                $htmlBody,
                null,
                [],
                [
                    'context_model'    => Event::class,
                    'context_model_id' => $event->id,
                ],
            );

            $log->update(['status' => 'sent']);
            ActivityLogger::log($event, 'event', 'Erstinfo an ' . $to . ' versendet (Vorlage: ' . $template->label . ').');

            return ['status' => 'sent', 'message' => 'Erstinfo an ' . $to . ' versendet.', 'email_log_id' => $log->id];
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed']);
            \Log::warning('[Events\\EventInitialInfoMailer] Versand fehlgeschlagen', [
                'event_id' => $event->id,
                'to'       => $to,
                'error'    => $e->getMessage(),
            ]);
            ActivityLogger::log($event, 'event', 'Erstinfo-Versand fehlgeschlagen: ' . $e->getMessage());
            return ['status' => 'failed', 'message' => 'Versand fehlgeschlagen: ' . $e->getMessage(), 'email_log_id' => $log->id];
        }
    }
}
