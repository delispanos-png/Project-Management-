<form method="post" action="{$actionUrl}" id="dynamicConfigFieldsForm" enctype="multipart/form-data">
    {$csrfToken}
    <input type="hidden" name="service_group_slug" value="{$serviceGroupSlug|escape}" />

    <div id="dynamicConfigFieldsError" class="alert alert-danger" style="display: none;"></div>

    {foreach $fields as $field}
        <div class="form-group mb-3" data-field-container="{$field.name|escape}">
            <label class="form-label">
                {$field.label|escape}
                {if $field.required}
                    <span class="text-danger">*</span>
                {/if}
            </label>

            <div class="field-input-container">
                {if $field.type == 'text' || $field.type == 'email' || $field.type == 'number' || $field.type == 'password' || $field.type == 'domain' || $field.type == 'date'}
                    <input
                            type="{$field.type|escape}"
                            name="configFields[{$field.name|escape}]"
                            value="{$values[$field.name]|default:''|escape}"
                            class="form-control config-field-input"
                            data-display-name="{$field.label|escape}"
                            {if $field.required}data-required="true"{/if}
                    />
                {elseif $field.type == 'textarea'}
                    <textarea
                            name="configFields[{$field.name|escape}]"
                            class="form-control config-field-input"
                            data-display-name="{$field.label|escape}"
                            {if $field.required}data-required="true"{/if}
                    >{$values[$field.name]|default:''|escape}</textarea>
                {elseif $field.type == 'select' && isset($field.options)}
                    <select
                            name="configFields[{$field.name|escape}]"
                            class="form-control config-field-input"
                            data-display-name="{$field.label|escape}"
                            {if $field.required}data-required="true"{/if}
                    >
                        {foreach $field.options as $optionValue => $optionLabel}
                            <option value="{$optionValue|escape}" {if isset($values[$field.name]) && $values[$field.name] == $optionValue}selected{/if}>
                                {$optionLabel|escape}
                            </option>
                        {/foreach}
                    </select>
                {elseif $field.type == 'checkbox' || $field.type == 'boolean'}
                    <div class="checkbox">
                        <label>
                            <input
                                    type="checkbox"
                                    name="configFields[{$field.name|escape}]"
                                    value="1"
                                    {if $values[$field.name]}checked{/if}
                                    {if $field.required}data-required="true"{/if}
                                    data-display-name="{$field.label|escape}"
                            />
                            {$field.description|escape}
                        </label>
                    </div>
                {/if}
            </div>

            <div class="alert alert-danger mt-1 error-block" style="display: none; padding: 5px 10px; margin-bottom: 0;">
                <span class="error-msg-text"></span>
            </div>

            {if $field.type != 'checkbox' && $field.type != 'boolean' && $field.description}
                <small class="form-text text-muted">{$field.description|escape}</small>
            {/if}
        </div>
    {/foreach}
</form>

<script>
    $(document).ready(function() {
        let form = $('#dynamicConfigFieldsForm');
        let btn = $('#btnDynamicConfigFieldsSubmit');

        function validateEmail(email) {
            let re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        btn.off('click').on('click', function(e) {
            e.preventDefault();

            let hasError = false;

            form.find('.form-group').removeClass('has-error');
            form.find('.error-block').hide();
            form.find('.config-field-input').css('border-color', '');
            $('#dynamicConfigFieldsError').hide();

            form.find('input, select, textarea').each(function() {
                let input = $(this);
                let container = input.closest('.form-group');
                let errorBlock = container.find('.error-block');
                let displayName = input.data('display-name') || 'field';
                let value = input.val();
                let isRequired = input.data('required') === true;

                let currentError = "";

                if (isRequired) {
                    let isEmpty = input.is(':checkbox') ? !input.is(':checked') : (!value || $.trim(value) === '');
                    if (isEmpty) {
                        currentError = "The " + displayName.toLowerCase() + " field is required.";
                    }
                }

                if (!currentError && input.attr('type') === 'email' && value) {
                    if (!validateEmail(value)) {
                        currentError = "The " + displayName.toLowerCase() + " must be a valid email address.";
                    }
                }

                if (currentError) {
                    hasError = true;
                    container.addClass('has-error');
                    input.css('border-color', '#a94442');
                    errorBlock.find('.error-msg-text').text(currentError);
                    errorBlock.show();
                }
            });

            if (hasError) {
                let firstError = form.find('.has-error').first();
                if (firstError.length) {
                    form.parent().animate({
                        scrollTop: firstError.position().top + form.parent().scrollTop() - 10
                    }, 500);
                }
                return false;
            }

            btn.prop('disabled', true).addClass('disabled');

            $.post(form.attr('action'), form.serialize(), function(data) {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    btn.prop('disabled', false).removeClass('disabled');
                    $('#dynamicConfigFieldsError').html(data.message).show();
                    form.parent().scrollTop(0);
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).removeClass('disabled');
                $('#dynamicConfigFieldsError').text('An unexpected error occurred. Please try again.').show();
            });
        });
    });
</script>
