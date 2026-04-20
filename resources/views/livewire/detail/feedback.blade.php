<div class="space-y-4">
    @php
        $audienceMap = [
            'participant' => 'Teilnehmer',
            'client'      => 'Kunde',
            'vendor'      => 'Dienstleister',
            'other'       => 'Sonstige',
        ];
    @endphp

    <x-ui-panel>
        <div class="p-4 flex justify-between items-center border-b border-[var(--ui-border)]">
            <div>
                <h3 class="text-sm font-bold text-[var(--ui-secondary)]">Feedback-Links</h3>
                <p class="text-[0.62rem] text-[var(--ui-muted)]">Oeffentlich teilbare Tokens fuer Feedback-Abgabe</p>
            </div>
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-3.5 h-3.5 inline') Neuer Link
            </x-ui-button>
        </div>

        @if($links->isEmpty())
            <div class="p-12 text-center">
                @svg('heroicon-o-chat-bubble-left-right', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Feedback-Links</p>
                <p class="text-xs text-[var(--ui-muted)]">Lege einen Link an und teile ihn mit Teilnehmern/Kunden.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
                            <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Label</th>
                            <th class="px-3 py-2 text-left text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Zielgruppe</th>
                            <th class="px-3 py-2 text-center text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Aktiv</th>
                            <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Aufrufe</th>
                            <th class="px-3 py-2 text-right text-[0.62rem] font-bold uppercase text-[var(--ui-muted)]">Antworten</th>
                            <th class="px-3 py-2 w-40"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($links as $link)
                            <tr class="border-b border-[var(--ui-border)]/60">
                                <td class="px-3 py-2 text-xs font-semibold text-[var(--ui-secondary)]">{{ $link->label }}</td>
                                <td class="px-3 py-2 text-xs text-[var(--ui-muted)]">{{ $audienceMap[$link->audience] ?? $link->audience }}</td>
                                <td class="px-3 py-2 text-center">
                                    <button wire:click="toggleActive({{ $link->id }})"
                                            class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full {{ $link->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $link->is_active ? 'Ja' : 'Nein' }}
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-xs font-mono text-right">{{ $link->view_count }}</td>
                                <td class="px-3 py-2 text-xs font-mono text-right">{{ $link->entries_count }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('events.public.feedback', ['token' => $link->token]) }}" target="_blank"
                                           class="text-xs text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                                            @svg('heroicon-o-eye', 'w-3.5 h-3.5') Public
                                        </a>
                                        <x-ui-button variant="danger-outline" size="sm"
                                                     wire:click="deleteLink({{ $link->id }})"
                                                     wire:confirm="Link löschen? Bestehende Antworten bleiben.">
                                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                        </x-ui-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui-panel>

    @if($total > 0)
        <x-ui-panel title="Durchschnittsbewertung" subtitle="Ø aus {{ $total }} Antworten">
            <div class="p-5 grid grid-cols-4 gap-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-primary)]">{{ $avg['overall'] ?? '—' }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Gesamt</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $avg['location'] ?? '—' }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Location</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $avg['catering'] ?? '—' }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Catering</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $avg['organization'] ?? '—' }}</p>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] uppercase">Organisation</p>
                </div>
            </div>
        </x-ui-panel>
    @endif

    @if($entries->isNotEmpty())
        <x-ui-panel title="Antworten" subtitle="Bis zu 50 letzte Feedbacks">
            <div class="divide-y divide-[var(--ui-border)]/40">
                @foreach($entries as $entry)
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="text-xs font-semibold text-[var(--ui-secondary)]">{{ $entry->name ?: 'Anonym' }}</span>
                                <span class="text-[0.62rem] text-[var(--ui-muted)] ml-2">{{ $entry->link?->label ?: 'Link entfernt' }}</span>
                            </div>
                            <span class="text-[0.62rem] text-[var(--ui-muted)] font-mono">{{ $entry->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex gap-3 text-[0.7rem] text-[var(--ui-muted)] mb-2">
                            <span>Gesamt: <strong>{{ $entry->rating_overall ?: '—' }}/5</strong></span>
                            <span>Location: <strong>{{ $entry->rating_location ?: '—' }}/5</strong></span>
                            <span>Catering: <strong>{{ $entry->rating_catering ?: '—' }}/5</strong></span>
                            <span>Organisation: <strong>{{ $entry->rating_organization ?: '—' }}/5</strong></span>
                        </div>
                        @if($entry->comment)
                            <p class="text-xs text-[var(--ui-secondary)] whitespace-pre-wrap">{{ $entry->comment }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui-panel>
    @endif

    <x-ui-modal wire:model="showCreateModal" size="md" :hideFooter="true">
        <x-slot name="header">Neuer Feedback-Link</x-slot>
        <form wire:submit.prevent="createLink" class="space-y-4">
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Label *</label>
                <input wire:model="newLabel" type="text" placeholder="z.B. Teilnehmer allgemein"
                       class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
            </div>
            <div>
                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Zielgruppe</label>
                <select wire:model="newAudience" class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs">
                    @foreach($audienceMap as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="$set('showCreateModal', false)">Abbrechen</x-ui-button>
                <x-ui-button type="submit" variant="primary" size="sm">Link erzeugen</x-ui-button>
            </div>
        </form>
    </x-ui-modal>
</div>
