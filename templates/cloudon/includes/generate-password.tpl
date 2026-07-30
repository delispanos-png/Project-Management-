<form action="#" id="frmGeneratePassword" class="form-horizontal">
    <div class="modal fade" id="modalGeneratePassword">
        <div class="modal-dialog generatepassword">
            <div class="modal-content">
                <div class="modal-header ">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><i class="ico-plus"></i></button>
                    <h4 class="modal-title">
                    {$LANG.generatePassword.title}
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger hidden" id="generatePwLengthError">
                        {$LANG.generatePassword.lengthValidationError}
                    </div>
                    <div class="form-group">
                        <label for="generatePwLength" class="col-sm-4 control-label">{$LANG.generatePassword.pwLength}</label>
                        <div class="col-sm-8">
                            <input type="number" min="8" max="64" value="12" step="1" class="form-control input-inline input-inline-100" id="inputGeneratePasswordLength">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="generatePwOutput" class="col-sm-4 control-label">{$LANG.generatePassword.generatedPw}</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="inputGeneratePasswordOutput">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12 text-right">
                            <button type="submit" class="btn btn-success btn-extrasmall">
                            <i class="fas fa-plus fa-fw"></i>
                            {$LANG.generatePassword.generateNew}
                            </button>
                            <button type="button" class="btn btn-prussian btn-extrasmall copy-to-clipboard" data-clipboard-target="#inputGeneratePasswordOutput">
                            <i class="fas fa-copy fa-fw"></i>
                            {$LANG.copy}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-small" data-dismiss="modal">
                    {$LANG.close}
                    </button>
                    <button type="button" class="btn btn-prussian btn-small" id="btnGeneratePasswordInsert" data-clipboard-target="#inputGeneratePasswordOutput">
                    {$LANG.generatePassword.copyAndInsert}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>