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
            if (rootEl.offsetParent !== null) return self._boot();

            if (typeof window.IntersectionObserver !== 'undefined') {
                const observer = new IntersectionObserver(function (entries) {
                    if (entries[0].isIntersecting && !self._editor) {
                        observer.disconnect();
                        self._boot();
                    }
                });
                observer.observe(rootEl);
            }

            const poll = (attempts = 300) => {
                if (self._editor || attempts <= 0) return;
                if (rootEl.offsetParent !== null) return self._boot();
                setTimeout(() => poll(attempts - 1), 200);
            };
            poll();
        },

        _boot() {
            const self = this;
            const target = document.getElementById(this.uid);
            if (!target) return;

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
     x-init="$nextTick(() => setTimeout(() => init(), 50))">
    <textarea id="{{ $uid }}" style="opacity:0; position:absolute; left:-9999px; pointer-events:none;"></textarea>
</div>
