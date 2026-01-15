<?php
namespace app\customs\zapi\common\validators\z;

use app\customs\zapi\common\helpers\CArrayHelper;

class CCollectionValidator extends CValidator
{

    /**
     * If set to false, the array cannot be empty.
     *
     * @var bool
     */
    public $empty = false;

    /**
     * Name of a field that must have unique values in the whole collection.
     *
     * @var string
     */
    public $uniqueField;

    /**
     * Second field to be used as a uniqueness criteria.
     *
     * @var string
     */
    public $uniqueField2;

    /**
     * Error message if the array is empty.
     *
     * @var string
     */
    public $messageEmpty;

    /**
     * Error message if the given value is not an array.
     *
     * @var string
     */
    public $messageInvalid;

    /**
     * Error message if duplicate objects exist.
     *
     * @var string
     */
    public $messageDuplicate;

    /**
     * Checks if the given array of objects is valid.
     *
     * @param array $value
     *
     * @return bool
     */
    public function validate($value)
    {
        if (!is_array($value)) {
            $this->error($this->messageInvalid, $this->stringify($value));

            return false;
        }

        // check if it's empty
        if (!$this->empty && !$value) {
            $this->error($this->messageEmpty);

            return false;
        }

        // check for objects with duplicate values
        if ($this->uniqueField) {
            if ($duplicate = CArrayHelper::findDuplicate($value, $this->uniqueField, $this->uniqueField2)) {
                if ($this->uniqueField2 === null) {
                    $this->error($this->messageDuplicate, $duplicate[$this->uniqueField]);
                } else {
                    $this->error($this->messageDuplicate,
                        $duplicate[$this->uniqueField], $duplicate[$this->uniqueField2]
                    );
                }

                return false;
            }
        }

        return true;
    }

}
