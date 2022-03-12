<?php
use yii\helpers\Html;

$language = Yii::$app->language;
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>
<?= $content ?>
<?php $this->endBody() ?>
<script>
<?php $this->beginBlock('jsScript') ?>

<?php $this->endBlock() ?>
</script>
<?php $this->registerJs($this->blocks['jsScript'], \yii\web\View::POS_END); ?>
</body>
</html>
<?php $this->endPage() ?>