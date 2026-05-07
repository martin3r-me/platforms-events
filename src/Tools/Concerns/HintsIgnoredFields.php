<?php

namespace Platform\Events\Tools\Concerns;

/**
 * Liefert pro silently-verworfenem Top-Level-Feld einen kurzen Hinweis,
 * was das LLM stattdessen tun soll. Damit landen typische Falsch-Annahmen
 * (z.B. "description" wie in anderen Modulen) nicht in einer Sackgasse.
 */
trait HintsIgnoredFields
{
    /** Map: Feldname (silently ignoriert) => Hinweis fuer das LLM. */
    protected const IGNORED_FIELD_HINTS = [
        'description' => 'Events haben kein Beschreibungs-Feld. Lege Beschreibungen / Briefings via events.notes.POST an (type: "liefertext" | "absprache" | "vereinbarung").',
        'note'        => 'Einzelne Notizen werden nicht am Event gespeichert. Nutze events.notes.POST.',
        'notes'       => 'Notizen werden nicht am Event gespeichert. Nutze events.notes.POST je Eintrag.',
        'title'       => 'Event-Titel heisst "name" (nicht "title").',
        'pax_min'     => 'Personenzahl wird pro EventDay (pers_von/pers_bis) gepflegt – beim POST via "pax"/"default_pax" einmalig fuer alle Tage setzbar.',
        'pax_max'     => 'Personenzahl wird pro EventDay (pers_von/pers_bis) gepflegt – beim POST via "pax"/"default_pax" einmalig fuer alle Tage setzbar.',
        'guests'      => 'Personenzahl wird pro EventDay (pers_von/pers_bis) gepflegt – beim POST via "pax"/"default_pax" einmalig fuer alle Tage setzbar.',
        'date'        => 'Verwende "start_date" (und ggf. "end_date") im Format YYYY-MM-DD.',
    ];

    /**
     * @param  array<int,string>  $ignored  silently verworfene Felder
     * @return array<string,string>         hint-map nur fuer Felder, fuer die ein Hinweis hinterlegt ist
     */
    protected function hintsForIgnored(array $ignored): array
    {
        $hints = [];
        foreach ($ignored as $field) {
            if (isset(self::IGNORED_FIELD_HINTS[$field])) {
                $hints[$field] = self::IGNORED_FIELD_HINTS[$field];
            }
        }
        return $hints;
    }
}
