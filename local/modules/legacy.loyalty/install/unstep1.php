<?php

use \Bitrix\Main\Localization\Loc;

if(!check_bitrix_sessid())
    return;

Loc::loadMessages(__FILE__);
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
<?=bitrix_sessid_post()?>
    <input type="hidden" name="lang" value="<?echo LANGUAGE_ID?>">
    <input type="hidden" name="id" value="legacy.loyalty">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <?echo CAdminMessage::ShowMessage(Loc::getMessage("LEGACY_LOYALTY_UNINSTALL_WARN"))?>
    <p><?echo Loc::getMessage("LEGACY_LOYALTY_UNINSTALL_SAVEDATA")?></p>
    <p><input type="checkbox" name="savedata" id="savedata" value="Y"><label for="savedata"><?echo Loc::getMessage("LEGACY_LOYALTY_UNINSTALL_SAVETABLES")?></label></p>
    <input type="submit" name="" value="<?echo Loc::getMessage("LEGACY_LOYALTY_UNINSTALL_DELETE")?>">
</form>
