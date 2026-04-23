@props([
    'wireProperty' => '',   {{-- z.B. 'contractText' oder 'tplForm.html_content' --}}
    'initial'      => '',
    'height'       => 600,
    'uniqueId'     => null,
])

@php
    $uid = $uniqueId ?: ('tiny-' . uniqid());
@endphp

{{-- Script-Definition VOR dem div, damit window.tinymceEditor verfuegbar ist,
     wenn Alpine das x-data-Attribut evaluiert. --}}
@once
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
window.tinymceEditor = function (opts) {
    return {
        uid: opts.uid,
        wireProperty: opts.wireProperty,
        initial: opts.initial || '',
        height: opts.height || 600,
        _editor: null,
        _wireId: null,

        init() {
            const rootEl = this.$root;
            const wireEl = rootEl.closest('[wire\\:id]');
            this._wireId = wireEl ? wireEl.getAttribute('wire:id') : null;
            this._waitForTinyAndObserve(rootEl);

            // Livewire-Dispatch "tinymce-set-content" -> Content aktualisieren
            // (z.B. wenn Modal mit anderem Datensatz wieder geoeffnet wird)
            const self = this;
            window.addEventListener('tinymce-set-content', function (e) {
                if (!e.detail || e.detail.uid !== self.uid) return;
                const content = e.detail.content || '';
                if (self._editor && self._editor.initialized) {
                    self._editor.setContent(content);
                } else {
                    self.initial = content;
                }
            });

            // Cleanup erfolgt via x-init Return-Function (siehe Template),
            // damit bei Livewire-Morph kein verwaister TinyMCE-Geist bleibt.
        },

        destroy() {
            this._removeStaleEditor();
            this._editor = null;
        },

        _removeStaleEditor() {
            if (!window.tinymce) return;
            try {
                const existing = window.tinymce.get(this.uid);
                if (existing) existing.remove();
            } catch (e) {}
        },

        _waitForTinyAndObserve(rootEl) {
            const self = this;
            const ensureTiny = (attempts = 80) => {
                if (typeof window.tinymce !== 'undefined') return self._observe(rootEl);
                if (attempts <= 0) return;
                setTimeout(() => ensureTiny(attempts - 1), 100);
            };
            ensureTiny();
        },

        _observe(rootEl) {
            const self = this;
            // "Alive" meint: wir haben lokal einen Editor UND sein Container
            // haengt noch im aktuellen Dokument UND die globale tinymce-Registry
            // fuer unsere UID zeigt auf genau diese Instanz. Das filtert Faelle,
            // in denen der Morph die DOM-Elemente ersetzt und eine Alt-Instanz
            // nur noch als Geist zurueckbleibt.
            const editorIsAlive = () => {
                if (!self._editor) return false;
                const container = self._editor.getContainer?.();
                if (!container || !document.body.contains(container)) return false;
                if (window.tinymce && window.tinymce.get(self.uid) !== self._editor) return false;
                return true;
            };

            if (rootEl.offsetParent !== null && !editorIsAlive()) return self._boot();

            if (typeof window.IntersectionObserver !== 'undefined') {
                const observer = new IntersectionObserver(function (entries) {
                    if (entries[0].isIntersecting && !editorIsAlive()) {
                        self._boot();
                    }
                });
                observer.observe(rootEl);
                // Observer bleibt persistent: falls Modal-DOM zerschossen wird
                // (z.B. durch Livewire-Re-Render), koennen wir neu booten.
            }

            // Zusaetzliches Polling als Sicherheitsnetz
            const poll = () => {
                if (rootEl.offsetParent !== null && !editorIsAlive()) {
                    self._boot();
                }
                setTimeout(poll, 500);
            };
            setTimeout(poll, 500);
        },

        _boot() {
            const self = this;
            const target = document.getElementById(this.uid);
            if (!target) return;

            // Vorherige tinymce-Instanz fuer diese UID sauber entfernen, sonst
            // scheitert tinymce.init() still oder initialisiert gegen ein
            // losgeloestes DOM.
            self._removeStaleEditor();
            self._editor = null;

            // Frische Initial-Daten aus Livewire holen (wire:ignore friert den
            // Blade-interpolierten Wert auf der ersten Seitenrenderung ein).
            try {
                if (this._wireId && window.Livewire) {
                    const wire = window.Livewire.find(this._wireId);
                    const parts = this.wireProperty.split('.');
                    let val = wire?.get(parts[0]);
                    for (let i = 1; i < parts.length && val; i++) val = val[parts[i]];
                    if (typeof val === 'string' && val.length > 0) this.initial = val;
                }
            } catch (e) {}

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
                images_upload_handler: function (blobInfo, progress) {
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
                    self._editor = editor;
                    editor.on('init', function () {
                        editor.setContent(self.initial || '');
                    });
                    editor.on('change keyup undo redo blur', function () {
                        self._syncToLivewire(editor.getContent());
                    });
                },
            });
        },

        _syncToLivewire(html) {
            if (!this._wireId || !window.Livewire) return;
            try {
                const wire = window.Livewire.find(this._wireId);
                if (wire) wire.set(this.wireProperty, html, false);
            } catch (e) {}
        },
    };
};
</script>
@endonce

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
     x-init="$nextTick(() => setTimeout(() => init(), 50)); return () => destroy();">
    <textarea id="{{ $uid }}" style="opacity:0; position:absolute; left:-9999px; pointer-events:none;"></textarea>
</div>
