jQuery(document).ready(function(){
    if(typeof window.langPasswordWeak === 'undefined'){
        window.langPasswordWeak = "Weak";
    }
    if(typeof window.langPasswordModerate === 'undefined'){
        window.langPasswordModerate = "Moderate";
    }
    if(typeof window.langPasswordStrong === 'undefined'){
        window.langPasswordStrong = "Strong";
    }

    jQuery("#newpw").keyup(function () {
        var pwvalue = jQuery("#newpw").val();
        var pwstrength = getPasswordStrength(pwvalue);
        jQuery("#pwstrength").html(langPasswordStrong);
        jQuery("#pwstrengthpos").css("background-color","#33CC00");

        var errorThreshold = !isNaN(parseInt(jQuery(this).data('error-threshold'))) ? jQuery(this).data('error-threshold') : 50;
        var warningThreshold = !isNaN(parseInt(jQuery(this).data('warning-threshold'))) ? jQuery(this).data('warning-threshold') : 75;

        if (pwstrength<warningThreshold) {
            jQuery("#pwstrength").html(langPasswordModerate);
            jQuery("#pwstrengthpos").css("background-color","#ff6600");
        }
        if (pwstrength<errorThreshold) {
            jQuery("#pwstrength").html(langPasswordWeak);
            jQuery("#pwstrengthpos").css("background-color","#cc0000");
        }
        jQuery("#pwstrengthpos").css("width",pwstrength);
        jQuery("#pwstrengthneg").css("width",100-pwstrength);
    });
});

/**
 * Whether a candidate password satisfies the WHMCS\Security\PasswordPolicy rules for this
 * field, read from data-min-length/data-max-length/data-requires-numeric/data-requires-alphabetic
 * attributes on the field itself.
 *
 * A field that carries none of these attributes (e.g. a service/module password, which this
 * policy does not govern) reports every check as passing, leaving the strength score as the
 * only signal on that field - unchanged from before this function existed.
 */
function passwordMeetsPolicy(password, $field)
{
    var minLength = parseInt($field.data('min-length'), 10) || 0;
    var maxLength = parseInt($field.data('max-length'), 10);
    var requiresNumeric = $field.data('requires-numeric') == 1;
    var requiresAlphabetic = $field.data('requires-alphabetic') == 1;

    return (!minLength || password.length >= minLength)
        && (isNaN(maxLength) || password.length <= maxLength)
        && (!requiresNumeric || /[0-9]/.test(password))
        && (!requiresAlphabetic || /[a-zA-Z]/.test(password));
}

function registerFormPasswordStrengthFeedback()
{
    var $field = jQuery(this);
    var password = $field.val();
    passwordStrength = getPasswordStrength(password);

    var errorThreshold = !isNaN(parseInt($field.data('error-threshold'))) ? $field.data('error-threshold') : 50;
    var warningThreshold = !isNaN(parseInt($field.data('warning-threshold'))) ? $field.data('warning-threshold') : 75;

    if (!passwordMeetsPolicy(password, $field)) {
        // The strength score alone cannot tell a user their password will be rejected - it has
        // no notion of a minimum length or of required character classes. A password that scores
        // well by that measure but breaks the policy must still show as weak, not strong.
        textLabel = langPasswordWeak;
        cssClass = 'danger';
    } else if (passwordStrength >= warningThreshold) {
        textLabel = langPasswordStrong;
        cssClass = 'success';
    } else if (passwordStrength >= errorThreshold) {
        textLabel = langPasswordModerate;
        cssClass = 'warning';
    } else {
        textLabel = langPasswordWeak;
        cssClass = 'danger';
    }
    jQuery("#passwordStrengthTextLabel").html(langPasswordStrength + ': ' + passwordStrength + '% ' + textLabel);
    jQuery("#passwordStrengthMeterBar").css('width', passwordStrength + '%').attr('aria-valuenow', passwordStrength);
    var ver = parseInt($.fn.tooltip.Constructor.VERSION);
    switch (ver) {
        case 3:
            jQuery("#passwordStrengthMeterBar").removeClass('progress-bar-success progress-bar-warning progress-bar-danger').addClass('progress-bar-' + cssClass);
            break;
        default:
            jQuery("#passwordStrengthMeterBar").removeClass('bg-danger bg-warning bg-success').addClass('bg-' + cssClass);
            break;
    }
}

function getPasswordStrength(pw){
    var pwlength=(pw.length);
    if(pwlength>5)pwlength=5;
    var numnumeric=pw.replace(/[0-9]/g,"");
    var numeric=(pw.length-numnumeric.length);
    if(numeric>3)numeric=3;
    var symbols=pw.replace(/\W/g,"");
    var numsymbols=(pw.length-symbols.length);
    if(numsymbols>3)numsymbols=3;
    var numupper=pw.replace(/[A-Z]/g,"");
    var upper=(pw.length-numupper.length);
    if(upper>3)upper=3;
    var pwstrength=((pwlength*10)-20)+(numeric*10)+(numsymbols*15)+(upper*10);
    if(pwstrength<0){pwstrength=0}
    if(pwstrength>100){pwstrength=100}
    return pwstrength;
}

function showStrengthBar() {
    if(typeof window.langPasswordStrength === 'undefined'){
        window.langPasswordStrength = "Password Strength";
    }
    if(typeof window.langPasswordWeak === 'undefined'){
        window.langPasswordWeak = "Weak";
    }
    document.write('<table align="center" style="width:auto;"><tr><td>'+langPasswordStrength+':</td><td><div id="pwstrengthpos" style="position:relative;float:left;width:0px;background-color:#33CC00;border:1px solid #000;border-right:0px;">&nbsp;</div><div id="pwstrengthneg" style="position:relative;float:left;width:100px;background-color:#efefef;border:1px solid #000;border-left:0px;">&nbsp;</div></td><td><div id="pwstrength">'+langPasswordWeak+'</div></td></tr></table>');
}
