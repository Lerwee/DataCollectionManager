<?php

namespace app\customs\zapi\services\exports;

use app\common\base\BaseService;
use app\customs\zapi\common\export\writers\CExportWriter;
use Yii;
use yii\base\Exception;

abstract class BaseExport extends BaseService
{
    /**
     * @var string format
     */
    public $format = 'xml';

    /**
     * @var string raw
     */
    public $raw = true;

    /**
     * @var string version
     */
    public $version = '6.4';

    /**
     * @var string prefix
     */
    public $prefix = 'lwops';

    /**
     * @var boolean Export all
     */
    public $isAny = false;

    /**
     * @var array Array with data that must be exported.
     */
    protected $data;

	protected $options;

    /**
     * @var array Array with data fields that must be exported.
     */
    protected $dataFields;
    
    /**
     * @var ExportBuilder
     */
    protected $builder;

    
    /**
     * @var CExportWriter
     */
    protected $writer;

    /**
     * @return $this
     */
    public function load(array $options)
    {
        $vars = get_object_vars($this);
        foreach ($vars as $var => $def) {
            if (array_key_exists($var, $options)) {
                $this->{$var} = $options[$var];
            }
        }

        $this->options = array_merge([
			'hosts' => [],
			'templates' => [],
			'template_groups' => [],
			'host_groups' => [],
			'images' => [],
			'maps' => [],
			'mediaTypes' => []
		], array_key_exists('options',$options) ? $options['options'] : []);

        return $this;
    }

    /**
	 * Setter for builder property.
	 *
	 * @param ExportBuilder $builder
	 */
	public function setBuilder(ExportBuilder $builder) {
		$this->builder = $builder;
        return $this;
	}

    public function setWriter(CExportWriter $writer)
    {   
		$this->writer = $writer;
        return $this;
    }


    public function getFileName(): string
    {
        $name = strtolower(substr(basename(str_replace('\\', '/', static::class)), 0, -strlen('export')));
        return "{$this->prefix}_export_{$name}.{$this->format}"; 
    }
}
