@props([
    'wireProperty' => '',   {{-- z.B. 'contractText' oder 'tplForm.html_content' --}}
    'initial'      => '',
    'height'       => 600,
    'uniqueId'     => null,
])

@php
    $uid = $uniqueId ?: ('tiny-' . uniqid());
@endphp

<div wire:ignore
     x-data="tinymceEditor({
        uid: @js($uid),
        wireProperty: @js($wireProperty),
        initial: @js($initial),
        height: {{ (int) $height }},
     })"
     x-init="init()"
     x-on:livewire-update-content.window="reload($event.detail)">
    <textarea :id="uid" style="visibility: hidden;"></textarea>
</div>

@once
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js"></script>
    <script>
        window.tinymceEditor = function (opts) {
            return {
                uid: opts.uid,
                wireProperty: opts.wireProperty,
                initial: opts.initial || '',
                height: opts.height || 600,
                _editor: null,

                init() {
                    const self = this;
                    const boot = () => {
                        if (typeof window.tinymce === 'undefined') {
                            setTimeout(boot, 80);
                            return;
                        }
                        window.tinymce.init({
                            selector: '#' + self.uid,
                            license_key: 'gpl',
                            height: self.height,
                            menubar: 'file edit view insert format table',
                            plugins: 'lists table pagebreak hr wordcount image link autolink code',
                            toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | pagebreak hr | removeformat | code',
                            image_title: true,
                            image_dimensions: true,
                            image_caption: false,
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
                                editor.on('change keyup undo redo', function () {
                                    const html = editor.getContent();
                                    if (window.Livewire) {
                                        try {
                                            const wire = Livewire.find(
                                                editor.getElement().closest('[wire\\:id]')?.getAttribute('wire:id')
                                            );
                                            if (wire) wire.set(self.wireProperty, html, false);
                                        } catch (e) {}
                                    }
                                });
                            },
                        });
                    };
                    boot();

                    // Cleanup bei Modal-Close / Komponenten-Destroy
                    document.addEventListener('livewire:navigating', () => this.destroy());
                },

                reload(detail) {
                    if (detail?.uid !== this.uid || !this._editor) return;
                    this._editor.setContent(detail.content || '');
                },

                destroy() {
                    try {
                        if (this._editor) this._editor.remove();
                    } catch (e) {}
                    this._editor = null;
                },
            };
        };
    </script>
@endonce
