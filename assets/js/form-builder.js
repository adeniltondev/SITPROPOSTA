/**
 * FORMA4 Imobiliária – form-builder.js
 * Construtor visual de formulários dinâmicos (criação/edição)
 *
 * Gerencia um array de campos em memória, renderiza o preview
 * e serializa para JSON antes de enviar ao servidor.
 */

(function () {
    'use strict';

    // =========================================================
    // ESTADO
    // =========================================================
    /** @type {Array<Object>} Lista de campos do formulário */
    var fields = [];
    var editingIndex = -1; // índice do campo sendo editado no painel lateral

    // Referências DOM
    var fieldsList    = document.getElementById('fieldsList');
    var fieldsInput   = document.getElementById('fieldsJson');    // input hidden
    var editorPanel   = document.getElementById('fieldEditorPanel');
    var editorForm    = document.getElementById('fieldEditorForm');

    // Contador para IDs únicos
    var idCounter = Date.now();

    // =========================================================
    // INICIALIZAÇÃO
    // =========================================================
    document.addEventListener('DOMContentLoaded', function () {
        // Carrega campos existentes (edição de formulário)
        if (fieldsInput && fieldsInput.value) {
            try {
                fields = JSON.parse(fieldsInput.value);
                if (!Array.isArray(fields)) fields = [];
            } catch (e) {
                fields = [];
            }
        }

        renderFields();

        // Botões "Adicionar campo"
        document.querySelectorAll('.add-field-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                addField(btn.getAttribute('data-type'));
            });
        });

        // Submit do editor de campo (painel lateral)
        if (editorForm) {
            editorForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveFieldEditor();
            });
        }

        // Botão cancelar edição
        var cancelBtn = document.getElementById('cancelEditBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeEditor);
        }

        // Sync do JSON antes de submeter o formulário principal
        var mainForm = document.getElementById('formBuilderForm');
        if (mainForm) {
            mainForm.addEventListener('submit', function () {
                syncJSON();
            });
        }
    });

    // =========================================================
    // RENDERIZAÇÃO DA LISTA DE CAMPOS
    // =========================================================
    function renderFields() {
        if (!fieldsList) return;

        if (fields.length === 0) {
            fieldsList.innerHTML = '<div class="fields-empty">Nenhum campo adicionado.<br>Use os botões ao lado para criar campos.</div>';
            syncJSON();
            return;
        }

        fieldsList.innerHTML = '';

        fields.forEach(function (field, index) {
            var card = document.createElement('div');
            card.className = 'field-card';
            card.setAttribute('data-index', index);

            card.innerHTML = [
                '<div class="field-card-drag" title="Arrastar">',
                '  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">',
                '    <path d="M5 9h14M5 15h14"/>',
                '  </svg>',
                '</div>',
                '<div class="field-card-info">',
                '  <div class="field-card-label">' + escHtml(field.label || field.name) + (field.required ? ' <span style="color:#ef4444;font-size:11px;">*</span>' : '') + '</div>',
                '  <div class="field-card-meta">',
                '    <span class="field-type-tag">' + escHtml(field.type) + '</span>',
                '    &nbsp;<span class="text-muted text-sm">' + escHtml(field.name) + '</span>',
                '  </div>',
                '</div>',
                '<div class="field-card-actions">',
                '  <button type="button" class="btn-icon" onclick="FB.editField(' + index + ')" title="Editar">',
                '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
                '  </button>',
                '  <button type="button" class="btn-icon" onclick="FB.moveUp(' + index + ')" title="Mover acima" ' + (index === 0 ? 'disabled' : '') + '>',
                '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg>',
                '  </button>',
                '  <button type="button" class="btn-icon" onclick="FB.moveDown(' + index + ')" title="Mover abaixo" ' + (index === fields.length - 1 ? 'disabled' : '') + '>',
                '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>',
                '  </button>',
                '  <button type="button" class="btn-icon" onclick="FB.removeField(' + index + ')" title="Remover" style="color:#ef4444;">',
                '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>',
                '  </button>',
                '</div>',
            ].join('');

            fieldsList.appendChild(card);
        });

        syncJSON();
    }

    // =========================================================
    // ADICIONAR CAMPO
    // =========================================================
    function addField(type) {
        var newField = {
            id:          'f' + (++idCounter),
            name:        type + '_' + idCounter,
            label:       labelFromType(type),
            type:        type,
            required:    false,
            placeholder: '',
            options:     '',
        };

        fields.push(newField);
        renderFields();
        editField(fields.length - 1); // Abre editor para o novo campo
    }

    // =========================================================
    // EDITAR CAMPO (abre painel lateral)
    // =========================================================
    function editField(index) {
        if (index < 0 || index >= fields.length) return;

        editingIndex = index;
        var field    = fields[index];

        // Popula o editor
        setValue('editor_label',       field.label       || '');
        setValue('editor_name',        field.name        || '');
        setValue('editor_type',        field.type        || 'text');
        setValue('editor_placeholder', field.placeholder || '');
        setValue('editor_required',    field.required ? '1' : '');
        setValue('editor_options',     field.options     || '');

        toggleOptionsField(field.type);

        // Mostra painel
        if (editorPanel) {
            editorPanel.style.display = 'block';
            var labelInput = document.getElementById('editor_label');
            if (labelInput) labelInput.focus();
        }

        // Tipo muda → toggle options
        var typeSelect = document.getElementById('editor_type');
        if (typeSelect) {
            typeSelect.onchange = function () { toggleOptionsField(this.value); };
        }

        // Auto-gera name a partir do label
        var labelEl = document.getElementById('editor_label');
        var nameEl  = document.getElementById('editor_name');
        if (labelEl && nameEl) {
            labelEl.oninput = function () {
                // Só auto-gera se o name ainda parece automático
                if (nameEl._userEdited) return;
                nameEl.value = slugifyName(labelEl.value);
            };
            nameEl.oninput = function () { nameEl._userEdited = true; };
            nameEl._userEdited = false;
        }
    }

    // =========================================================
    // SALVAR EDIÇÃO DO CAMPO
    // =========================================================
    function saveFieldEditor() {
        if (editingIndex < 0 || editingIndex >= fields.length) return;

        var label       = (getValue('editor_label')       || '').trim();
        var name        = (getValue('editor_name')        || '').trim();
        var type        = getValue('editor_type')         || 'text';
        var placeholder = getValue('editor_placeholder')  || '';
        var required    = !!document.getElementById('editor_required').checked;
        var options     = getValue('editor_options')      || '';

        if (!label) { alert('O rótulo do campo é obrigatório.'); return; }
        if (!name)  { alert('O nome (chave) do campo é obrigatório.'); return; }

        // Valida name: letras, números e underscore apenas
        if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(name)) {
            alert('O nome do campo deve conter apenas letras, números e underscore, e começar com letra ou underscore.');
            return;
        }

        // Verifica duplicidade de name (exceto o próprio campo)
        var dup = fields.some(function (f, i) { return i !== editingIndex && f.name === name; });
        if (dup) { alert('Já existe um campo com este nome. Use um nome único.'); return; }

        fields[editingIndex] = {
            id:          fields[editingIndex].id,
            name:        name,
            label:       label,
            type:        type,
            required:    required,
            placeholder: placeholder,
            options:     options,
        };

        closeEditor();
        renderFields();
    }

    // =========================================================
    // FECHAR EDITOR
    // =========================================================
    function closeEditor() {
        editingIndex = -1;
        if (editorPanel) editorPanel.style.display = 'none';
    }

    // =========================================================
    // MOVER CAMPO
    // =========================================================
    function moveUp(index) {
        if (index <= 0) return;
        var tmp = fields[index];
        fields[index]     = fields[index - 1];
        fields[index - 1] = tmp;
        renderFields();
    }

    function moveDown(index) {
        if (index >= fields.length - 1) return;
        var tmp = fields[index];
        fields[index]     = fields[index + 1];
        fields[index + 1] = tmp;
        renderFields();
    }

    // =========================================================
    // REMOVER CAMPO
    // =========================================================
    function removeField(index) {
        if (!confirm('Remover o campo "' + (fields[index] && fields[index].label) + '"?')) return;
        fields.splice(index, 1);
        if (editingIndex === index) closeEditor();
        renderFields();
    }

    // =========================================================
    // TOGGLE OPÇÕES (para select)
    // =========================================================
    function toggleOptionsField(type) {
        var optionsGroup = document.getElementById('optionsGroup');
        if (!optionsGroup) return;
        optionsGroup.style.display = (type === 'select' || type === 'checkbox') ? 'block' : 'none';
    }

    // =========================================================
    // SYNC JSON -> input hidden
    // =========================================================
    function syncJSON() {
        if (fieldsInput) {
            fieldsInput.value = JSON.stringify(fields);
        }
    }

    // =========================================================
    // HELPERS
    // =========================================================
    function escHtml(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }

    function slugifyName(str) {
        return str
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .substring(0, 50) || 'campo';
    }

    function labelFromType(type) {
        var map = {
            'text':     'Texto',
            'number':   'Número',
            'date':     'Data',
            'select':   'Seleção',
            'checkbox': 'Checkbox',
            'textarea': 'Área de Texto',
        };
        return map[type] || 'Campo';
    }

    function getValue(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function setValue(id, val) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el.type === 'checkbox') {
            el.checked = !!val;
        } else {
            el.value = val;
        }
    }

    // =========================================================
    // API PÚBLICA (chamada via atributos onclick no HTML)
    // =========================================================
    window.FB = {
        editField:   editField,
        moveUp:      moveUp,
        moveDown:    moveDown,
        removeField: removeField,
    };

})();
