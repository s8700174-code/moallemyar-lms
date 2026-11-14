<?php defined('ABSPATH') || exit; ?><!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>سامانه آموزشی هوشمند | ورود والدین و معلم</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url(MYR_URL.'assets/login/style.css?ver='.MYR_VERSION); ?>">
<link rel="stylesheet" href="<?php echo esc_url(MYR_URL.'assets/login/connection.css?ver='.MYR_VERSION); ?>">
</head><body><?php echo MYR_Core::instance()->frontend->login(); ?>
<script>window.MYR_LOGIN=<?php echo wp_json_encode(array('rest'=>rest_url('myr/v1/'),'assets'=>MYR_URL.'assets/login/'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="<?php echo esc_url(MYR_URL.'assets/login/script.js?ver='.MYR_VERSION); ?>"></script>
</body></html>
