<?php
/**
 * 作用于界面可配置项
 * 安装时，会自动添加到`lw_config`表
 * @example
 * ```php
 * return [
 *     configure_item('name1', 'label1', FORM_TEXT, 'tip1'),
 *     configure_group([
 *         configure_item('name2', 'label2', FORM_TEXT, 'tip2', 1),
 *         configure_item('name3', 'label3', FORM_FILE, 'tip3', 2),
 *     ], '模块参数')
 * ];
 * ```
 */
return [

];
