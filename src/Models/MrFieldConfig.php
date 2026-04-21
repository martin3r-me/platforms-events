<?php

namespace Platform\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Dynamische Konfiguration der Management-Report-Felder (Gruppen, Label, Auswahl-Optionen).
 */
class MrFieldConfig extends Model
{
    use SoftDeletes;

    protected $table = 'events_mr_field_configs';

    protected $fillable = [
        'uuid', 'user_id', 'team_id',
        'group_label', 'label', 'options', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'uuid'      => 'string',
        'options'   => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    /**
     * Farbe einer Option automatisch ableiten aus Position + Label-Hint.
     */
    public static function deriveOptionColor(int $index, int $total, string $label): string
    {
        $lc = mb_strtolower(trim($label));
        foreach (['n/a', 'nicht benötigt', 'nicht bentigt', 'unbekannt', 'keine rechnung'] as $needle) {
            if (str_contains($lc, $needle)) return 'gray';
        }
        if ($index === 0) return 'red';
        if ($index === $total - 1) return 'green';
        return 'yellow';
    }

    /**
     * Default-Konfiguration fuer ein neues Team einmalig in die DB schreiben.
     */
    public static function seedDefaultsFor(int $teamId, ?int $userId = null): void
    {
        if (self::where('team_id', $teamId)->exists()) return;

        $defaults = [
            ['group_label' => 'Logistik & Personal', 'label' => 'Logistik',              'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group_label' => 'Logistik & Personal', 'label' => 'Getränkelogistik',      'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group_label' => 'Logistik & Personal', 'label' => 'Personaldienstleister', 'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group_label' => 'Logistik & Personal', 'label' => 'Küchenpersonal',        'options' => ['fehlende Eingabe', 'Bedarf', 'kein Bedarf', 'OK', 'abgeschlossen']],
            ['group_label' => 'Produktion',          'label' => 'Küchenproduktion',      'options' => ['fehlende Eingabe', 'in Bearbeitung', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group_label' => 'Produktion',          'label' => 'Ort Küchenproduktion',  'options' => ['fehlende Eingabe', 'in Klärung', 'bestätigt', 'nicht benötigt']],
            ['group_label' => 'Rechnungen',          'label' => 'A-Conto (Location)',    'options' => ['noch nicht erstellt', 'erstellt', 'versandt', 'bezahlt', 'keine Rechnung']],
            ['group_label' => 'Rechnungen',          'label' => 'A-Conto (Catering)',    'options' => ['noch nicht erstellt', 'erstellt', 'versandt', 'bezahlt', 'keine Rechnung']],
            ['group_label' => 'Rechnungen',          'label' => 'Abschlussrechnung',     'options' => ['keine Rechnung', 'noch nicht erstellt', 'erstellt', 'versandt', 'bezahlt']],
            ['group_label' => 'Controlling',         'label' => 'Getränke Lieferant',    'options' => ['fehlende Eingabe', 'ausstehend', 'OK', 'abgeschlossen', 'nicht benötigt']],
            ['group_label' => 'Controlling',         'label' => 'Ablaufplan (KÜLO)',     'options' => ['unbekannt (PL)', 'fehlende Eingabe', 'vorhanden', 'in Bearbeitung', 'abgeschlossen']],
            ['group_label' => 'Controlling',         'label' => 'Getränkeverbrauch',     'options' => ['fehlende Eingabe', 'ausstehend', 'OK', 'abgeschlossen', 'nicht benötigt']],
        ];

        foreach ($defaults as $i => $d) {
            $total = count($d['options']);
            $coloredOptions = [];
            foreach ($d['options'] as $j => $opt) {
                $coloredOptions[] = ['label' => $opt, 'color' => self::deriveOptionColor($j, $total, $opt)];
            }
            self::create([
                'team_id'     => $teamId,
                'user_id'     => $userId,
                'group_label' => $d['group_label'],
                'label'       => $d['label'],
                'options'     => $coloredOptions,
                'sort_order'  => $i,
                'is_active'   => true,
            ]);
        }
    }
}
