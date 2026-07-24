{* CloudOn — δημόσια φόρμα αιτημάτων → lead στο CRM *}
{if $cpmFormOff}
    <div class="alert alert-info">Η φόρμα δεν είναι διαθέσιμη αυτή τη στιγμή. Επικοινωνήστε μαζί μας μέσω <a href="submitticket.php">ticket</a>.</div>
{elseif $cpmSent}
    <div style="max-width:560px;margin:30px auto;text-align:center">
        <div style="font-size:52px">✅</div>
        <h3>Λάβαμε το αίτημά σας!</h3>
        <p style="color:#8291a9">Θα επικοινωνήσουμε μαζί σας το συντομότερο — συνήθως εντός της ίδιας εργάσιμης ημέρας.</p>
    </div>
{else}
    <div style="max-width:560px;margin:0 auto">
        <h3>Πείτε μας τι χρειάζεστε</h3>
        <p style="color:#8291a9">Συμπληρώστε τα στοιχεία σας και θα σας καλέσουμε.</p>
        {if $cpmErr}<div class="alert alert-danger">{$cpmErr}</div>{/if}
        <form method="post">
            <input type="hidden" name="fts" value="{$cpmTs}">
            <div style="position:absolute;left:-6000px" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="form-group"><label>Ονοματεπώνυμο *</label><input type="text" name="contact" class="form-control" required value="{$smarty.post.contact|default:''}"></div>
            <div class="form-group"><label>Εταιρεία</label><input type="text" name="company" class="form-control" value="{$smarty.post.company|default:''}"></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required value="{$smarty.post.email|default:''}"></div>
            <div class="form-group"><label>Τηλέφωνο</label><input type="text" name="phone" class="form-control" value="{$smarty.post.phone|default:''}"></div>
            <div class="form-group"><label>Τι χρειάζεστε; *</label><textarea name="message" class="form-control" rows="5" required>{$smarty.post.message|default:''}</textarea></div>
            <button type="submit" class="btn btn-primary btn-lg">Αποστολή αιτήματος</button>
        </form>
    </div>
{/if}
