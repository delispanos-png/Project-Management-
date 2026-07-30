{if $loginpage eq 0 and $templatefile ne "clientregister"}<!-- login and register page without the default header and footer -->
                                        </div><!-- /.main-content -->
                                    <div class="clearfix"></div>
                                </div><!-- end row -->
                            </div><!-- end container -->
                        </section><!-- end main body section -->
                    </div><!-- end page wrapper -->
                </div><!-- end main body -->
            </div><!-- end inner content -->
        </div><!-- end content -->
    </div><!-- end wrapper -->
</div><!-- end main container -->
<section id="footer" class="footer-collapsed"><!-- footer start -->
<div class="footer-copyright">Copyright &copy; {$date_year} {$companyname}.</div>
<div class="footer-menu">
<a href="{$WEB_ROOT}/gdpr.php" target="_self">{$LANG.personaldatamanagement}</a>
<a href="{$WEB_ROOT}/terms.php" target="_self">{$LANG.ordertos}</a>
{if !$loggedin}
<a href="{$WEB_ROOT}/register.php" target="_self">{$LANG.register}</a>
{else}
<a href="clientarea.php" target="_self">{$LANG.clientareanavhome}</a>
{/if}
<a href="{$WEB_ROOT}/contact.php" target="_self">{$LANG.contactus}</a>
</div>
</section><!-- footer end -->
{/if}
{if $loggedin}<span id="gravataremail" class="hidden">{$clientsdetails.email}</span><!-- gravatar email -->{/if}
<div id="fullpage-overlay" class="hidden">
    <div class="outer-wrapper">
        <div class="inner-wrapper">
            <img src="{$WEB_ROOT}/assets/img/overlay-spinner.svg">
            <br>
            <span class="msg"></span>
        </div>
    </div>
</div>
<div class="modal system-modal fade" id="modalAjax" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content panel-primary">
            <div class="modal-header panel-heading">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                    <span class="sr-only">{$LANG.close}</span>
                </button>
                <h4 class="modal-title">title</h4>
            </div>
            <div class="modal-body panel-body">
                {$LANG.loading}
            </div>
            <div class="modal-footer panel-footer">
                <div class="pull-left loader">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    {$LANG.loading}
                </div>
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    {$LANG.close}
                </button>
                <button type="button" class="btn btn-primary modal-submit">
                    {$LANG.submit}
                </button>
            </div>
        </div>
    </div>
</div>
{include file="$template/includes/generate-password.tpl"}
{$footeroutput}

<script>
 if ($("p:contains('Powered by')").length) {
 $("p:contains('Powered by')").hide();
 }
</script>

</body>
</html>