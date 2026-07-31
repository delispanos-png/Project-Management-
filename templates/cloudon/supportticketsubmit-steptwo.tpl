{if $errormessage}
    {include file="$template/includes/alert.tpl" type="error" errorshtml=$errormessage}
{/if}

<form method="post" action="{$smarty.server.PHP_SELF}?step=3" enctype="multipart/form-data" role="form">

    <div class="row">
        <div class="form-group col-sm-4">
            <label for="inputName">{$LANG.supportticketsclientname}</label>
            <input type="text" name="name" id="inputName" value="{$name}" class="form-control{if $loggedin} disabled{/if}"{if $loggedin} disabled="disabled"{/if} />
        </div>
        <div class="form-group col-sm-5">
            <label for="inputEmail">{$LANG.supportticketsclientemail}</label>
            <input type="email" name="email" id="inputEmail" value="{$email}" class="form-control{if $loggedin} disabled{/if}"{if $loggedin} disabled="disabled"{/if} />
        </div>
    </div>
    <div class="row">
        <div class="form-group col-sm-10">
            <label for="inputSubject">{$LANG.supportticketsticketsubject}</label>
            <input type="text" name="subject" id="inputSubject" value="{$subject}" class="form-control" />
        </div>
    </div>
    <div class="row">
        <div class="form-group col-sm-3">
            <label for="inputDepartment">{$LANG.supportticketsdepartment}</label>
            <select name="deptid" id="inputDepartment" class="form-control" onchange="refreshCustomFields(this)">
                {foreach from=$departments item=department}
                    <option value="{$department.id}"{if $department.id eq $deptid} selected="selected"{/if}>
                        {$department.name}
                    </option>
                {/foreach}
            </select>
        </div>
        {if $relatedservices}
            {* Η επιλογή υπηρεσίας είναι υποχρεωτική: χωρίς αυτήν ο τεχνικός δεν
               ξέρει σε ποιο από τα προϊόντα του πελάτη αναφέρεται το αίτημα και
               χάνεται χρόνος σε διευκρινίσεις. Υποχρεωτική είναι η ΕΠΙΛΟΓΗ, όχι
               η υπηρεσία — υπάρχει ρητή επιλογή για γενικά ερωτήματα. *}
            <div class="form-group col-sm-5">
                <label for="inputRelatedService">{$LANG.relatedservice} <span class="cnp-req" aria-hidden="true">*</span></label>
                <select name="relatedservice" id="inputRelatedService" class="form-control" data-cnp-pick="1" data-cnp-msg="{$LANG.cnp_pick_service_err|escape:'html'}">
                    <option value="" data-cnp-placeholder="1" selected="selected">{$LANG.cnp_pick_service}</option>
                    {foreach from=$relatedservices item=relatedservice}
                        <option value="{$relatedservice.id}">
                            {$relatedservice.name} ({$relatedservice.status})
                        </option>
                    {/foreach}
                    <option value="">{$LANG.cnp_general_query}</option>
                </select>
            </div>
        {/if}
        <div class="form-group col-sm-3">
            <label for="inputPriority">{$LANG.supportticketspriority}</label>
            <select name="urgency" id="inputPriority" class="form-control">
                <option value="High"{if $urgency eq "High"} selected="selected"{/if}>
                    {$LANG.supportticketsticketurgencyhigh}
                </option>
                <option value="Medium"{if $urgency eq "Medium" || !$urgency} selected="selected"{/if}>
                    {$LANG.supportticketsticketurgencymedium}
                </option>
                <option value="Low"{if $urgency eq "Low"} selected="selected"{/if}>
                    {$LANG.supportticketsticketurgencylow}
                </option>
            </select>
        </div>
    </div>
    {* Department custom fields (e.g. "PharmacyOne Version") — shown right after the
       department selection so they're near the top, not buried under attachments. *}
    <div id="customFieldsContainer">
        {include file="$template/supportticketsubmit-customfields.tpl"}
    </div>

    <div class="form-group">
        <label for="inputMessage">{$LANG.contactmessage}</label>
        <textarea name="message" id="inputMessage" rows="12" class="form-control markdown-editor" data-auto-save-name="client_ticket_open">{$message}</textarea>
    </div>

    <div class="row form-group">
        <div class="col-sm-12">
            <label for="inputAttachments">{$LANG.supportticketsticketattachments}</label>
        </div>
        <div class="col-sm-9">
            <input type="file" name="attachments[]" id="inputAttachments" class="form-control" />
            <div id="fileUploadsContainer"></div>
        </div>
        <div class="col-sm-3">
            <button type="button" class="btn btn-default btn-block" onclick="extraTicketAttachment()">
                <i class="fas fa-plus"></i> {$LANG.addmore}
            </button>
        </div>
        <div class="col-xs-12 ticket-attachments-message text-muted">
            {$LANG.supportticketsallowedextensions}: {$allowedfiletypes} ({lang key="maxFileSize" fileSize="$uploadMaxFileSize"})
        </div>
    </div>

    <div id="autoAnswerSuggestions" class="well hidden"></div>

    <div class="text-center margin-bottom">
        {include file="$template/includes/captcha.tpl"}
    </div>

    <p class="text-center">
        <input type="submit" id="openTicketSubmit" value="{$LANG.supportticketsticketsubmit}" class="btn btn-primary disable-on-click{$captcha->getButtonClass($captchaForm)}" />
        <a href="supporttickets.php" class="btn btn-default">{$LANG.cancel}</a>
    </p>

</form>

{if $kbsuggestions}
    <script>
        jQuery(document).ready(function() {
            getTicketSuggestions();
        });
    </script>
{/if}

{* ── Φραγή υποβολής όσο δεν έχει επιλεγεί υπηρεσία ─────────────────────────
   Δεν χρησιμοποιούμε HTML required: η επιλογή «γενικό ερώτημα» έχει κενή τιμή
   (όπως την περιμένει το WHMCS) και θα κοβόταν άδικα. Ξεχωρίζουμε τη θέση.

   ΠΡΟΣΟΧΗ: όλο το script είναι σε {literal} — χωρίς αυτό η Smarty ερμηνεύει
   αγκύλες του JavaScript (π.χ. {block:'center'}) ως δικές της ετικέτες και
   η σελίδα σκάει. Το μήνυμα έρχεται από data-attribute, όχι από {$LANG} εδώ. *}
{literal}
<script>
(function () {
  var sel = document.getElementById('inputRelatedService');
  if (!sel || !sel.getAttribute('data-cnp-pick')) return;
  var form = sel.form;
  if (!form) return;

  var msg = document.createElement('div');
  msg.className = 'cnp-field-err';
  msg.setAttribute('role', 'alert');
  msg.textContent = sel.getAttribute('data-cnp-msg') || 'Διάλεξε υπηρεσία.';
  sel.parentNode.appendChild(msg);

  /* Το WHMCS προσθέτει κλάση «disabled» στο κουμπί με το κλικ, ώστε να μη
     γίνει διπλή υποβολή — αλλά ΔΕΝ την αφαιρεί ποτέ. Όταν εμείς μπλοκάρουμε
     την υποβολή, το κουμπί έμενε νεκρό και ο πελάτης δεν μπορούσε να στείλει
     ούτε αφού διόρθωνε το λάθος. */
  function reenable() {
    var b = form.querySelector('#openTicketSubmit') ||
            form.querySelector('input[type=submit], button[type=submit]');
    if (b) { b.classList.remove('disabled'); b.removeAttribute('disabled'); }
  }

  function pending() {
    var o = sel.options[sel.selectedIndex];
    return !o || o.getAttribute('data-cnp-placeholder') === '1';
  }
  sel.addEventListener('change', function () {
    if (!pending()) { msg.style.display = 'none'; sel.classList.remove('cnp-invalid'); }
  });
  form.addEventListener('submit', function (e) {
    if (pending()) {
      e.preventDefault();
      e.stopPropagation();
      msg.style.display = '';
      sel.classList.add('cnp-invalid');
      reenable();
      sel.focus();
      if (sel.scrollIntoView) { sel.scrollIntoView(true); }
    }
  }, true);

  // Ίδιο πρόβλημα όταν μπλοκάρει η ενσωματωμένη επικύρωση του browser
  // (π.χ. κενό υποχρεωτικό πεδίο): το κουμπί μένει «disabled».
  form.addEventListener('invalid', reenable, true);
})();
</script>
{/literal}
