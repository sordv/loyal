<?php

$MESS['LEGACY_LOYALTY_MAIL_EV_BONUS_ORDER_NAME'] = 'Legacy Loyalty: начисление бонусов за заказ';
$MESS['LEGACY_LOYALTY_MAIL_EV_BONUS_ORDER_DESC'] = '#BONUS_NAME#, #BONUS_AMOUNT#, #ACTIVATE_DATE#, #ORDER_ID#, #ORDER_ACCOUNT#';
$MESS['LEGACY_LOYALTY_MAIL_MT_BONUS_ORDER_SUBJ'] = 'Вам начислены #BONUS_NAME# за заказ';
$MESS['LEGACY_LOYALTY_MAIL_MT_BONUS_ORDER_BODY'] = '<p>Здравствуйте, #NAME# #LAST_NAME#!</p>
<p>Начислено <b>#BONUS_AMOUNT#</b> (#BONUS_NAME#).</p>
<p>Дата активации: <b>#ACTIVATE_DATE#</b>.</p>
<p>Заказ: № #ORDER_ACCOUNT# (ID #ORDER_ID#).</p>
<p>С уважением, #SITE_NAME#</p>';

$MESS['LEGACY_LOYALTY_MAIL_EV_BONUS_ADMIN_NAME'] = 'Legacy Loyalty: начисление бонусов администратором';
$MESS['LEGACY_LOYALTY_MAIL_EV_BONUS_ADMIN_DESC'] = '#BONUS_NAME#, #BONUS_AMOUNT#, #ACTIVATE_DATE#, #EXPIRE_DATE#';
$MESS['LEGACY_LOYALTY_MAIL_MT_BONUS_ADMIN_SUBJ'] = 'Вам начислены #BONUS_NAME#';
$MESS['LEGACY_LOYALTY_MAIL_MT_BONUS_ADMIN_BODY'] = '<p>Здравствуйте, #NAME# #LAST_NAME#!</p>
<p>Администратор начислил <b>#BONUS_AMOUNT#</b> (#BONUS_NAME#).</p>
<p>Дата активации: <b>#ACTIVATE_DATE#</b>.</p>
<p>#EXPIRE_DATE_TEXT#</p>
<p>С уважением, #SITE_NAME#</p>';

$MESS['LEGACY_LOYALTY_MAIL_EV_BONUS_EXPIRE_NAME'] = 'Legacy Loyalty: предупреждение об истечении бонусов';
$MESS['LEGACY_LOYALTY_MAIL_EV_BONUS_EXPIRE_DESC'] = '#BONUS_NAME#, #BONUS_AMOUNT#, #EXPIRE_DATE#, #DAYS_BEFORE#';
$MESS['LEGACY_LOYALTY_MAIL_MT_BONUS_EXPIRE_SUBJ'] = 'Скоро сгорят #BONUS_NAME#';
$MESS['LEGACY_LOYALTY_MAIL_MT_BONUS_EXPIRE_BODY'] = '<p>Здравствуйте, #NAME# #LAST_NAME#!</p>
<p>Через <b>#DAYS_BEFORE#</b> дн. истекает срок <b>#BONUS_AMOUNT#</b> (#BONUS_NAME#).</p>
<p>Дата списания: <b>#EXPIRE_DATE#</b>.</p>
<p>С уважением, #SITE_NAME#</p>';

$MESS['LEGACY_LOYALTY_MAIL_EV_LEVEL_NAME'] = 'Legacy Loyalty: изменение уровня лояльности';
$MESS['LEGACY_LOYALTY_MAIL_EV_LEVEL_DESC'] = '#OLD_LEVEL_NAME#, #NEW_LEVEL_NAME#, #OLD_LEVEL_ID#, #NEW_LEVEL_ID#';
$MESS['LEGACY_LOYALTY_MAIL_MT_LEVEL_SUBJ'] = 'Ваш уровень в программе лояльности изменён';
$MESS['LEGACY_LOYALTY_MAIL_MT_LEVEL_BODY'] = '<p>Здравствуйте, #NAME# #LAST_NAME#!</p>
<p>Ваш уровень изменён.</p>
<p>Было: <b>#OLD_LEVEL_NAME#</b></p>
<p>Стало: <b>#NEW_LEVEL_NAME#</b></p>
<p>С уважением, #SITE_NAME#</p>';
