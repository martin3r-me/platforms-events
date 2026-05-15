@props([
    'wireProperty' => '',   {{-- z.B. 'contractText' oder 'tplForm.html_content' --}}
    'initial'      => '',
    'height'       => 600,
    'uniqueId'     => null,
])

@php
    $uid = $uniqueId ?: ('tiny-' . uniqid());
@endphp

{{-- Registrierung als Alpine.data-Component statt window-Global:
     - vermeidet Race-Condition zwischen Script-Execution und Alpine-Init
     - @assets sorgt fuer wire:navigate-Persistenz (einmalig im <head>)
     - Definition liegt zusaetzlich auf window.tinymceEditor (Fallback) --}}
@assets
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
/**
 * TinyMCE-Editor fuer Livewire-Modals.
 *
 * Zustandsmodell:
 *   _editor   = tinymce-Instanz, wenn 'init' gefeuert hat (null sonst)
 *   _booting  = true, solange tinymce.init() laeuft, sonst false
 *
 * Sichtbarkeitserkennung per IntersectionObserver (kein Polling).
 * Ein einziger Boot-Pfad (_boot), der Stale-Instanzen entfernt und via
 * _booting-Guard nicht doppelt feuern kann. Bei Re-Open des Modals prueft
 * die View-Transition den DOM-Zustand des Containers – ist er weg, wird
 * sauber neu gebootet.
 */
function _tinymceEditorFactory(opts) {
    return {
        uid: opts.uid,
        wireProperty: opts.wireProperty,
        initial: opts.initial || '',
        height: opts.height || 600,
        _editor: null,
        _booting: false,
        _wireId: null,

        init() {
            const self = this;
            const rootEl = this.$root;
            const wireEl = rootEl.closest('[wire\\:id]');
            this._wireId = wireEl ? wireEl.getAttribute('wire:id') : null;

            // Content-Replace aus Livewire (z.B. Modal wird mit anderem
            // Datensatz wiederbefuellt).
            window.addEventListener('tinymce-set-content', function (e) {
                if (!e.detail || e.detail.uid !== self.uid) return;
                const content = e.detail.content || '';
                if (self._editor && self._editor.initialized) {
                    self._editor.setContent(content);
                } else {
                    self.initial = content;
                }
            });

            // Warten bis TinyMCE-Script geladen ist, dann Sichtbarkeit beobachten.
            this._whenTinyReady(function () {
                self._observe(rootEl);
            });
        },

        _whenTinyReady(done) {
            const tryLoad = function (attempts) {
                if (typeof window.tinymce !== 'undefined') return done();
                if (attempts <= 0) return;
                setTimeout(function () { tryLoad(attempts - 1); }, 50);
            };
            tryLoad(200); // max. 10s warten
        },

        /**
         * Beobachtet Sichtbarkeit. Jede Sichtbarkeitsaenderung triggert einen
         * Boot-Versuch; _boot() selbst entscheidet, ob neu gebootet werden muss.
         */
        _observe(rootEl) {
            const self = this;

            // Initial: falls schon sichtbar, sofort booten.
            if (rootEl.offsetParent !== null) self._boot();

            if (typeof IntersectionObserver !== 'undefined') {
                const obs = new IntersectionObserver(function (entries) {
                    if (entries[0].isIntersecting) self._boot();
                });
                obs.observe(rootEl);
            }
        },

        _isAlive() {
            if (!this._editor) return false;
            const container = this._editor.getContainer && this._editor.getContainer();
            return !!container && document.body.contains(container);
        },

        _boot() {
            const self = this;

            // Schon laufend oder gesund: nichts tun.
            if (self._booting) return;
            if (self._isAlive()) return;

            const target = document.getElementById(self.uid);
            if (!target) return;

            self._booting = true;
            self._editor = null;

            // Evtl. Alt-Instanz in der globalen Registry entfernen (z.B. nach
            // Livewire-Morph, der unser DOM geschreddert hat).
            try {
                const stale = window.tinymce.get(self.uid);
                if (stale) stale.remove();
            } catch (e) {}

            // Frische Initial-Daten aus Livewire lesen (Blade-Wert kann veraltet sein).
            self._refreshInitial();

            // Safety-Net: falls 'init' nie feuert, Flag nach 10s zuruecksetzen,
            // damit ein weiterer Sichtbarkeitswechsel neu booten kann.
            const bootingId = setTimeout(function () {
                self._booting = false;
            }, 10000);

            try {
                window.tinymce.init({
                    target: target,
                    license_key: 'gpl',
                    height: self.height,
                    min_height: self.height,
                    autoresize_bottom_margin: 16,
                    menubar: 'file edit view insert format table',
                    plugins: 'lists table pagebreak wordcount image link autolink code',
                    toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | pagebreak | removeformat | code',
                    image_title: true,
                    image_dimensions: true,
                    automatic_uploads: true,
                    images_upload_handler: function (blobInfo) {
                        return new Promise(function (resolve, reject) {
                            const fd = new FormData();
                            fd.append('file', blobInfo.blob(), blobInfo.filename());
                            const token = document.querySelector('meta[name=csrf-token]')?.content || '';
                            fetch('/events/contract-assets/upload', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                                body: fd,
                            })
                            .then(function (r) {
                                if (!r.ok) throw new Error('Upload fehlgeschlagen (HTTP ' + r.status + ')');
                                return r.json();
                            })
                            .then(function (data) {
                                if (!data?.location) throw new Error('Keine URL zurueckerhalten');
                                resolve(data.location);
                            })
                            .catch(function (err) {
                                reject(err.message || 'Upload fehlgeschlagen');
                            });
                        });
                    },
                    table_use_colgroups: false,
                    promotion: false,
                    branding: false,
                    convert_urls: false,
                    relative_urls: false,
                    remove_script_host: false,
                    content_style: 'body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.6; color: #1a1a1a; margin: 16px; }',
                    setup: function (editor) {
                        editor.on('init', function () {
                            clearTimeout(bootingId);
                            self._editor = editor;
                            self._booting = false;
                            editor.setContent(self.initial || '');
                        });
                        editor.on('remove', function () {
                            if (self._editor === editor) self._editor = null;
                        });
                        editor.on('change keyup undo redo blur', function () {
                            self._syncToLivewire(editor.getContent());
                        });
                    },
                });
            } catch (err) {
                clearTimeout(bootingId);
                self._booting = false;
                console.error('[tinymce-editor] init failed', err);
            }
        },

        _refreshInitial() {
            try {
                if (!this._wireId || !window.Livewire) return;
                const wire = window.Livewire.find(this._wireId);
                const parts = this.wireProperty.split('.');
                let val = wire?.get(parts[0]);
                for (let i = 1; i < parts.length && val !== undefined && val !== null; i++) {
                    val = val[parts[i]];
                }
                if (typeof val === 'string') this.initial = val;
            } catch (e) {}
        },

        _syncToLivewire(html) {
            if (!this._wireId || !window.Livewire) return;
            try {
                const wire = window.Livewire.find(this._wireId);
                if (wire) wire.set(this.wireProperty, html, false);
            } catch (e) {}
        },
    };
}

// 1) Window-Fallback fuer Code, der die Funktion direkt aufruft.
window.tinymceEditor = _tinymceEditorFactory;

// 2) Alpine-Registrierung — `x-data="tinymceEditor({...})"` wird so robust
//    aufgeloest, auch wenn Alpine bereits initialisiert war als das Script
//    nachtraeglich (z.B. via wire:navigate) injiziert wurde.
function _registerTinymceWithAlpine() {
    if (!window.Alpine || typeof window.Alpine.data !== 'function') return false;
    if (window.Alpine.__tinymceRegistered) return true;
    window.Alpine.data('tinymceEditor', _tinymceEditorFactory);
    window.Alpine.__tinymceRegistered = true;
    return true;
}
if (!_registerTinymceWithAlpine()) {
    document.addEventListener('alpine:init', _registerTinymceWithAlpine);
    document.addEventListener('alpine:initialized', _registerTinymceWithAlpine);
}
</script>
@endassets

<style>
    .tox-tinymce {
        display: flex !important;
        flex-direction: column !important;
    }
    .tox-tinymce .tox-edit-area {
        flex: 1 1 auto !important;
    }
    .tox-tinymce .tox-edit-area > iframe {
        height: 100% !important;
    }
</style>
<div wire:ignore
     x-data="tinymceEditor({ uid: @js($uid), wireProperty: @js($wireProperty), initial: @js((string) $initial), height: {{ (int) $height }} })"
     x-init="$nextTick(() => setTimeout(() => init(), 50))">
    <textarea id="{{ $uid }}" style="opacity:0; position:absolute; left:-9999px; pointer-events:none;"></textarea>
</div>
