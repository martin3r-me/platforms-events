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
                if (attempts <= 0) {
                    console.error('[tinymceEditor] TinyMCE CDN nicht geladen');
                    return;
                }
                setTimeout(() => ensureTiny(attempts - 1), 100);
            };
            ensureTiny();
        },

        _observe(rootEl) {
            const self = this;
            // Sofort bootn wenn bereits sichtbar
            if (rootEl.offsetParent !== null) return self._boot();

            // Sonst auf Sichtbarkeits-Change warten (Modal-Open)
            if (typeof window.IntersectionObserver !== 'undefined') {
                const observer = new IntersectionObserver(function (entries) {
                    if (entries[0].isIntersecting && !self._editor) {
                        observer.disconnect();
                        self._boot();
                    }
                });
                observer.observe(rootEl);
            } else {
                // Fallback: Polling
                const poll = () => {
                    if (rootEl.offsetParent !== null) {
                        if (!self._editor) self._boot();
                        return;
                    }
                    setTimeout(poll, 200);
                };
                poll();
            }
        },

        _boot() {
            const self = this;
            const target = document.getElementById(this.uid);
            if (!target) {
                console.error('[tinymceEditor] target not found:', this.uid);
                return;
            }

            window.tinymce.init({
                target: target,
                license_key: 'gpl',
                height: self.height,
                menubar: 'file edit view insert format table',
                plugins: 'lists table pagebreak wordcount image link autolink code',
                toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | pagebreak | removeformat | code',
                image_title: true,
                image_dimensions: true,
                automatic_uploads: true,
                images_upload_handler: function (blobInfo) {
                    return new Promise(function (resolve) {
                        const reader = new FileReader();
                        reader.onload = function () { resolve(reader.result); };
                        reader.readAsDataURL(blobInfo.blob());
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
                    const sync = function () {
                        const html = editor.getContent();
                        self._syncToLivewire(html);
                    };
                    editor.on('change keyup undo redo blur', sync);
                },
            }).catch(function (err) {
                console.error('[tinymceEditor] init failed', err);
            });
        },

        _syncToLivewire(html) {
            if (!this._wireId || !window.Livewire) return;
            try {
                const wire = window.Livewire.find(this._wireId);
                if (wire) wire.set(this.wireProperty, html, false);
            } catch (e) {
                console.warn('[tinymceEditor] sync failed', e);
            }
        },
    };
};
</script>
@endonce

<div wire:ignore
     x-data="tinymceEditor({ uid: @js($uid), wireProperty: @js($wireProperty), initial: @js((string) $initial), height: {{ (int) $height }} })"
     x-init="$nextTick(() => setTimeout(() => init(), 50))">
    <textarea id="{{ $uid }}" style="display:none;"></textarea>
</div>
