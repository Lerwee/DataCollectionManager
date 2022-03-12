<?php

namespace app\customs\zabbix\widgets;

use Yii;
use yii\web\View;
use yii\widgets\Block;

class JsBlock extends Block
{
    /**
     * @var null
     */
    public $key = null;

    /**
     * @var int
     */
    public $pos = View::POS_END;

    /**
     * @var boolean
     */
    public $vailed = true;

    /**
     * Ends recording a block.
     * This method stops output buffering and saves the rendering result as a named block in the view.
     */
    public function run()
    {
        if ($this->vailed) {
            $block = ob_get_clean();
            if ($this->renderInPlace) {
                throw new \Exception('not implemented yet ! ');
            }
            $block          = trim($block);
            $jsBlockPattern = '|^<script[^>]*>(?P<block_content>.+?)</script>$|is';
            if (preg_match($jsBlockPattern, $block, $matches)) {
                $block = $matches['block_content'];
            }

            $this->view->registerJs($block, $this->pos, $this->key);
        }
    }
}
