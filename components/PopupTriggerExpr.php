<?php

namespace app\customs\zapi\components;

use app\common\base\BaseComponent;
use app\common\components\Result;
use app\common\traits\ResultTrait;
use app\customs\zapi\common\helpers\CArrayHelper;
use app\customs\zapi\common\helpers\TriggerHelper;
use app\customs\zapi\common\parsers\CExpressionParser;
use app\customs\zapi\common\parsers\CHistFunctionParser;
use app\customs\zapi\common\parsers\CParser;
use app\customs\zapi\common\parsers\results\CExpressionParserResult;
use app\customs\zapi\common\validators\z\CExpressionValidator;
use app\customs\zapi\models\search\item\ItemPrototypeSearch;
use app\customs\zapi\models\search\item\ItemSearch;

/**
 * Class PopupTriggerExpr
 * @package app\customs\zapi\components
 */
class PopupTriggerExpr extends BaseComponent
{
    use ResultTrait;

    private $metrics = [];
    private $param1SecCount = [];
    private $param1Period = [];
    private $param1Sec = [];
    private $param1Str = [];
    private $param2SecCount = [];
    private $param2SecMode = [];
    private $param2SecCountMode = [];
    private $param3SecVal = [];
    private $param_find = [];
    private $param3SecPercent = [];
    private $paramForecast = [];
    private $paramTimeleft = [];
    private $allowedTypesAny = [];
    private $allowedTypesNumeric = [];
    private $allowedTypesStr = [];
    private $allowedTypesLog = [];
    private $allowedTypesInt = [];
    private $functions = [];
    private $operators = ['=', '<>', '>', '<', '>=', '<='];
    private $period_optional = [];
    private $period_seasons = [];

    public $input = [];

    public function init()
    {
        $this->metrics = [
            PARAM_TYPE_TIME => t('zapi', 'Time'),
            PARAM_TYPE_COUNTS => t('zapi', 'Count')
        ];

        /*
         * C - caption
         * T - type
         * M - metrics
         * A - asterisk
         */
        $this->param1SecCount = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => true
            ],
            'shift' => [
                'C' => t('zapi', 'Time shift'),
                'T' => T_PRS_INT,
                'A' => false
            ]
        ];

        $this->period_optional = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => false
            ],
            'shift' => [
                'C' => t('zapi', 'Time shift'),
                'T' => T_PRS_INT,
                'A' => false
            ]
        ];

        $this->param1Period = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'A' => true
            ],
            'period_shift' => [
                'C' => t('zapi', 'Period shift'),
                'T' => T_PRS_INT,
                'A' => true
            ]
        ];

        $this->period_seasons = [
            'last' => [
                'C' => t('zapi', 'Period') . ' (T)',
                'T' => T_PRS_INT,
                'A' => true
            ],
            'period_shift' => [
                'C' => t('zapi', 'Period shift'),
                'T' => T_PRS_INT,
                'A' => true
            ],
            'season_unit' => [
                'C' => t('zapi', 'Season'),
                'T' => T_PRS_STR,
                'A' => true,
                'options' => [
                    'h' => t('zapi', 'Hour'),
                    'd' => t('zapi', 'Day'),
                    'w' => t('zapi', 'Week'),
                    'M' => t('zapi', "Month"),
                    'y' => t('zapi', 'Year')
                ]
            ],
            'num_seasons' => [
                'C' => t('zapi', 'Number of seasons'),
                'T' => T_PRS_INT,
                'A' => true
            ]
        ];

        $this->param1Sec = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'A' => true
            ]
        ];

        $this->param1Str = [
            'pattern' => [
                'C' => 'V',
                'T' => T_PRS_STR,
                'A' => false
            ]
        ];

        $this->param2SecCount = [
            'pattern' => [
                'C' => 'V',
                'T' => T_PRS_STR,
                'A' => false
            ],
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => false
            ]
        ];

        $this->param2SecMode = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'A' => true
            ],
            'mode' => [
                'C' => 'Mode',
                'T' => T_PRS_STR,
                'A' => false
            ]
        ];

        $this->param2SecCountMode = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => true
            ],
            'shift' => [
                'C' => t('zapi', 'Time shift'),
                'T' => T_PRS_INT,
                'A' => false
            ],
            'mode' => [
                'C' => 'Mode',
                'T' => T_PRS_STR,
                'A' => false
            ]
        ];

        $this->param3SecVal = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => true
            ],
            'shift' => [
                'C' => t('zapi', 'Time shift'),
                'T' => T_PRS_INT,
                'A' => false
            ],
            'o' => [
                'C' => 'O',
                'T' => T_PRS_STR,
                'A' => false
            ],
            'v' => [
                'C' => 'V',
                'T' => T_PRS_STR,
                'A' => false
            ]
        ];

        $this->param_find = [
            'o' => [
                'C' => 'O',
                'T' => T_PRS_STR,
                'A' => false
            ],
            'v' => [
                'C' => 'V',
                'T' => T_PRS_STR,
                'A' => false
            ]
        ];

        $this->param3SecPercent = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => true
            ],
            'shift' => [
                'C' => t('zapi', 'Time shift'),
                'T' => T_PRS_INT,
                'A' => false
            ],
            'p' => [
                'C' => t('zapi', 'Percentage') . ' (P)',
                'T' => T_PRS_DBL,
                'A' => true
            ]
        ];

        $this->paramForecast = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => true
            ],
            'shift' => [
                'C' => t('zapi', 'Time shift'),
                'T' => T_PRS_INT,
                'A' => false
            ],
            'time' => [
                'C' => t('zapi', 'Time') . ' (t)',
                'T' => T_PRS_INT,
                'A' => true
            ],
            'fit' => [
                'C' => t('zapi', 'Fit'),
                'T' => T_PRS_STR,
                'A' => false
            ],
            'mode' => [
                'C' => t('zapi', 'Mode'),
                'T' => T_PRS_STR,
                'A' => false
            ]
        ];

        $this->paramTimeleft = [
            'last' => [
                'C' => t('zapi', 'Last of') . ' (T)',
                'T' => T_PRS_INT,
                'M' => $this->metrics,
                'A' => true
            ],
            'shift' => [
                'C' => t('zapi', 'Time shift'),
                'T' => T_PRS_INT,
                'A' => false
            ],
            't' => [
                'C' => t('zapi', 'Threshold'),
                'T' => T_PRS_DBL,
                'A' => true
            ],
            'fit' => [
                'C' => t('zapi', 'Fit'),
                'T' => T_PRS_STR,
                'A' => false
            ]
        ];

        $this->allowedTypesAny = [
            ITEM_VALUE_TYPE_FLOAT => 1,
            ITEM_VALUE_TYPE_STR => 1,
            ITEM_VALUE_TYPE_LOG => 1,
            ITEM_VALUE_TYPE_UINT64 => 1,
            ITEM_VALUE_TYPE_TEXT => 1
        ];

        $this->allowedTypesNumeric = [
            ITEM_VALUE_TYPE_FLOAT => 1,
            ITEM_VALUE_TYPE_UINT64 => 1
        ];

        $this->allowedTypesStr = [
            ITEM_VALUE_TYPE_STR => 1,
            ITEM_VALUE_TYPE_LOG => 1,
            ITEM_VALUE_TYPE_TEXT => 1
        ];

        $this->allowedTypesLog = [
            ITEM_VALUE_TYPE_LOG => 1
        ];

        $this->allowedTypesInt = [
            ITEM_VALUE_TYPE_UINT64 => 1
        ];

        $this->functions = [
            'abs' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'abs() - Absolute value'),
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'acos' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'acos() - The arccosine of a value as an angle, expressed in radians'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'ascii' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'ascii() - Returns the ASCII code of the leftmost character of the value'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'asin' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'asin() - The arcsine of a value as an angle, expressed in radians'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'atan' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'atan() - The arctangent of a value as an angle, expressed in radians'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'atan2' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'atan2() - The arctangent of the ordinate (value) and abscissa coordinates specified as an angle, expressed in radians'),
                'params' => $this->param1SecCount + [
                        'abscissa' => [
                            'C' => t('zapi', 'Abscissa'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'avg' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE, PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'avg() - Average value of a period T'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'between' => [
                'types' => [PRS_FUNCTION_TYPE_OPERATOR],
                'description' => t('zapi', 'between() - Checks if a value belongs to the given range (1 - in range, 0 - otherwise)'),
                'params' => $this->param1SecCount + [
                        'min' => [
                            'C' => t('zapi', 'Min'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ],
                        'max' => [
                            'C' => t('zapi', 'Max'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => ['=', '<>']
            ],
            'bitand' => [
                'types' => [PRS_FUNCTION_TYPE_BITWISE],
                'description' => t('zapi', 'bitand() - Bitwise AND'),
                'params' => $this->param1SecCount + [
                        'mask' => [
                            'C' => t('zapi', 'Mask'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesInt,
                'operators' => $this->operators
            ],
            'bitlength' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'bitlength() - Returns the length in bits'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'bitlshift' => [
                'types' => [PRS_FUNCTION_TYPE_BITWISE],
                'description' => t('zapi', 'bitlshift() - Bitwise shift left'),
                'params' => $this->param1SecCount + [
                        'bits' => [
                            'C' => t('zapi', 'Bits to shift'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesInt,
                'operators' => $this->operators
            ],
            'bitnot' => [
                'types' => [PRS_FUNCTION_TYPE_BITWISE],
                'description' => t('zapi', 'bitnot() - Bitwise NOT'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesInt,
                'operators' => $this->operators
            ],
            'bitor' => [
                'types' => [PRS_FUNCTION_TYPE_BITWISE],
                'description' => t('zapi', 'bitor() - Bitwise OR'),
                'params' => $this->param1SecCount + [
                        'mask' => [
                            'C' => t('zapi', 'Mask'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesInt,
                'operators' => $this->operators
            ],
            'bitrshift' => [
                'types' => [PRS_FUNCTION_TYPE_BITWISE],
                'description' => t('zapi', 'bitrshift() - Bitwise shift right'),
                'params' => $this->param1SecCount + [
                        'bits' => [
                            'C' => t('zapi', 'Bits to shift'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesInt,
                'operators' => $this->operators
            ],
            'bitxor' => [
                'types' => [PRS_FUNCTION_TYPE_BITWISE],
                'description' => t('zapi', 'bitxor() - Bitwise exclusive OR'),
                'params' => $this->param1SecCount + [
                        'mask' => [
                            'C' => t('zapi', 'Mask'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesInt,
                'operators' => $this->operators
            ],
            'bytelength' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'bytelength() - Returns the length in bytes'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'cbrt' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'cbrt() - Cube root'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'ceil' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'ceil() - Rounds up to the nearest greater integer'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'change' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'change() - Difference between last and previous value'),
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'changecount' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'changecount() - Number of changes between adjacent values, Mode (all - all changes, inc - only increases, dec - only decreases)'),
                'params' => $this->param2SecCountMode,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'char' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'char() - Returns the character which represents the given ASCII code'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesInt,
                'operators' => $this->operators
            ],
            'concat' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'concat() - Returns a string that is the result of concatenating value to string'),
                'params' => $this->param1SecCount + [
                        'string' => [
                            'C' => t('zapi', 'String'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'cos' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'cos() - The cosine of a value, where the value is an angle expressed in radians'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'cosh' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'cosh() - The hyperbolic cosine of a value'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'cot' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'cot() - The cotangent of a value, where the value is an angle expressed in radians'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'count' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'count() - Number of successfully retrieved values V (which fulfill operator O) for period T'),
                'params' => $this->param3SecVal,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'countunique' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'countunique() - The number of unique values'),
                'params' => $this->param3SecVal,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'date' => [
                'types' => [PRS_FUNCTION_TYPE_DATE_TIME],
                'description' => t('zapi', 'date() - Current date'),
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'dayofmonth' => [
                'types' => [PRS_FUNCTION_TYPE_DATE_TIME],
                'description' => t('zapi', 'dayofmonth() - Day of month'),
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'dayofweek' => [
                'types' => [PRS_FUNCTION_TYPE_DATE_TIME],
                'description' => t('zapi', 'dayofweek() - Day of week'),
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'degrees' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'degrees() - Converts a value from radians to degrees'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'e' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', "e() - Returns Euler's number"),
                'allowed_types' => $this->allowedTypesAny
            ],
            'exp' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', "exp() - Euler's number at a power of a value"),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'expm1' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', "expm1() - Euler's number at a power of a value minus 1"),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'find' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'find() - Check occurrence of pattern V (which fulfill operator O) for period T (1 - match, 0 - no match)'),
                'params' => $this->period_optional + $this->param_find,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => ['=', '<>']
            ],
            'first' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'first() - The oldest value in the specified time interval'),
                'params' => $this->param1Sec + $this->period_optional,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'floor' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'floor() - Rounds down to the nearest smaller integer'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'forecast' => [
                'types' => [PRS_FUNCTION_TYPE_PREDICTION],
                'description' => t('zapi', 'forecast() - Forecast for next t seconds based on period T'),
                'params' => $this->paramForecast,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'fuzzytime' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'fuzzytime() - Difference between item value (as timestamp) and Perseus server timestamp is less than or equal to T seconds (1 - true, 0 - false)'),
                'params' => $this->param1Sec,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => ['=', '<>']
            ],
            'in' => [
                'types' => [PRS_FUNCTION_TYPE_OPERATOR],
                'description' => t('zapi', 'in() - Checks if a value equals to one of the listed values (1 - equals, 0 - otherwise)'),
                'params' => $this->param1SecCount + [
                        'values' => [
                            'C' => t('zapi', 'Values'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesAny,
                'operators' => ['=', '<>']
            ],
            'insert' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'insert() - Inserts specified characters or spaces into a character string, beginning at a specified position in the string'),
                'params' => $this->param1SecCount + [
                        'start' => [
                            'C' => t('zapi', 'Start'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ],
                        'length' => [
                            'C' => t('zapi', 'Length'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ],
                        'replace' => [
                            'C' => t('zapi', 'Replacement'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'kurtosis' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'kurtosis() - Measures the "tailedness" of the probability distribution'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'last' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'last() - Last (most recent) T value'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'left' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'left() - Returns the leftmost count characters'),
                'params' => $this->param1SecCount + [
                        'count' => [
                            'C' => t('zapi', 'Count'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'length' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'length() - Length of last (most recent) T value in characters'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'log' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'log() - Natural logarithm'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'log10' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'log10() - Decimal logarithm'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'logeventid' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'logeventid() - Event ID of last log entry matching regular expression V for period T (1 - match, 0 - no match)'),
                'params' => $this->period_optional + $this->param1Str,
                'allowed_types' => $this->allowedTypesLog,
                'operators' => ['=', '<>']
            ],
            'logseverity' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'logseverity() - Log severity of the last log entry for period T'),
                'params' => $this->period_optional,
                'allowed_types' => $this->allowedTypesLog,
                'operators' => $this->operators
            ],
            'logsource' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'logsource() - Log source of the last log entry matching parameter V for period T (1 - match, 0 - no match)'),
                'params' => $this->period_optional + $this->param1Str,
                'allowed_types' => $this->allowedTypesLog,
                'operators' => ['=', '<>']
            ],
            'ltrim' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'ltrim() - Remove specified characters from the beginning of a string'),
                'params' => $this->param1SecCount + [
                        'chars' => [
                            'C' => t('zapi', 'Chars'),
                            'T' => T_PRS_STR,
                            'A' => false
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'mad' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'mad() - Median absolute deviation'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'max' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE, PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'max() - Maximum value for period T'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'mid' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'mid() - Returns a substring beginning at the character position specified by start for N characters'),
                'params' => $this->param1SecCount + [
                        'start' => [
                            'C' => t('zapi', 'Start'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ],
                        'length' => [
                            'C' => t('zapi', 'Length'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'min' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE, PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'min() - Minimum value for period T'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'mod' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'mod() - Division remainder'),
                'params' => $this->param1SecCount + [
                        'denominator' => [
                            'C' => t('zapi', 'Division denominator'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'monodec' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'monodec() - Check for continuous item value decrease (1 - data is monotonic, 0 - otherwise), Mode (strict - require strict monotonicity)'),
                'params' => $this->param2SecCountMode,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => ['=', '<>']
            ],
            'monoinc' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'monoinc() - Check for continuous item value increase (1 - data is monotonic, 0 - otherwise), Mode (strict - require strict monotonicity)'),
                'params' => $this->param2SecCountMode,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => ['=', '<>']
            ],
            'nodata' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'nodata() - No data received during period of time T (1 - true, 0 - false), Mode (strict - ignore proxy time delay in sending data)'),
                'params' => $this->param2SecMode,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => ['=', '<>']
            ],
            'now' => [
                'types' => [PRS_FUNCTION_TYPE_DATE_TIME],
                'description' => t('zapi', 'now() - Number of seconds since the Epoch'),
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'percentile' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'percentile() - Percentile P of a period T'),
                'params' => $this->param3SecPercent,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'pi' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'pi() - Returns the Pi constant'),
                'allowed_types' => $this->allowedTypesAny
            ],
            'power' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'power() - The power of a base value to a power value'),
                'params' => $this->param1SecCount + [
                        'power' => [
                            'C' => t('zapi', 'Power value'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'radians' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'radians() - Converts a value from degrees to radians'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'rand' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'rand() - A random integer value'),
                'allowed_types' => $this->allowedTypesAny
            ],
            'rate' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'rate() - Returns per-second average rate for monotonically increasing counters'),
                'params' => $this->param1Sec + $this->period_optional,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'repeat' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'repeat() - Returns a string composed of value repeated count times'),
                'params' => $this->param1SecCount + [
                        'count' => [
                            'C' => t('zapi', 'Count'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'replace' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'replace() - Search value for occurrences of pattern, and replace with replacement'),
                'params' => $this->param1SecCount + [
                        'pattern' => [
                            'C' => t('zapi', 'Pattern'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ],
                        'replace' => [
                            'C' => t('zapi', 'Replacement'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'right' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'right() - Returns the rightmost count characters'),
                'params' => $this->param1SecCount + [
                        'count' => [
                            'C' => t('zapi', 'Count'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'round' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'round() - Rounds a value to decimal places'),
                'params' => $this->param1SecCount + [
                        'decimals' => [
                            'C' => t('zapi', 'Decimal places'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'rtrim' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'rtrim() - Removes specified characters from the end of a string'),
                'params' => $this->param1SecCount + [
                        'chars' => [
                            'C' => t('zapi', 'Chars'),
                            'T' => T_PRS_STR,
                            'A' => false
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'signum' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'signum() - Returns -1 if a value is negative, 0 if a value is zero, 1 if a value is positive'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'sin' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'sin() - The sine of a value, where the value is an angle expressed in radians'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'sinh' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'sinh() - The hyperbolic sine of a value'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'skewness' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'skewness() - Measures the asymmetry of the probability distribution'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'sqrt' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'sqrt() - Square root of a value'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'stddevpop' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'stddevpop() - Population standard deviation'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'stddevsamp' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'stddevsamp() - Sample standard deviation'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'sum' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE, PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'sum() - Sum of values of a period T'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'sumofsquares' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'sumofsquares() - The sum of squares'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'tan' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'tan() - The tangent of a value'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'time' => [
                'types' => [PRS_FUNCTION_TYPE_DATE_TIME],
                'description' => t('zapi', 'time() - Current time'),
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'timeleft' => [
                'types' => [PRS_FUNCTION_TYPE_PREDICTION],
                'description' => t('zapi', 'timeleft() - Time to reach threshold estimated based on period T'),
                'params' => $this->paramTimeleft,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'trendavg' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'trendavg() - Average value of a period T with exact period shift'),
                'params' => $this->param1Period,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'baselinedev' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'baselinedev() - Returns the number of deviations between data periods in seasons and the last data period'),
                'params' => $this->period_seasons,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'baselinewma' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'baselinewma() - Calculates baseline by averaging data periods in seasons'),
                'params' => $this->period_seasons,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'trendcount' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'trendcount() - Number of successfully retrieved values for period T'),
                'params' => $this->param1Period,
                'allowed_types' => $this->allowedTypesAny,
                'operators' => $this->operators
            ],
            'trendmax' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'trendmax() - Maximum value for period T with exact period shift'),
                'params' => $this->param1Period,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'trendmin' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'trendmin() - Minimum value for period T with exact period shift'),
                'params' => $this->param1Period,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'trendstl' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'trendstl() - Anomaly detection for period T'),
                'params' => [
                    'last' => [
                        'C' => t('zapi', 'Evaluation period') . ' (T)',
                        'T' => T_PRS_INT,
                        'A' => true
                    ],
                    'period_shift' => [
                        'C' => t('zapi', 'Period shift'),
                        'T' => T_PRS_INT,
                        'A' => true
                    ],
                    'detect_period' => [
                        'C' => t('zapi', 'Detection period'),
                        'T' => T_PRS_STR,
                        'A' => true
                    ],
                    'season' => [
                        'C' => t('zapi', 'Season'),
                        'T' => T_PRS_INT,
                        'A' => true
                    ],
                    'deviations' => [
                        'C' => t('zapi', 'Deviations'),
                        'T' => T_PRS_DBL,
                        'A' => false
                    ],
                    'algorithm' => [
                        'C' => t('zapi', 'Algorithm'),
                        'T' => T_PRS_STR,
                        'A' => false
                    ],
                    'season_window' => [
                        'C' => t('zapi', 'Season deviation window'),
                        'T' => T_PRS_INT,
                        'A' => false
                    ]
                ],
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'trendsum' => [
                'types' => [PRS_FUNCTION_TYPE_HISTORY],
                'description' => t('zapi', 'trendsum() - Sum of values of a period T with exact period shift'),
                'params' => $this->param1Period,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'trim' => [
                'types' => [PRS_FUNCTION_TYPE_STRING],
                'description' => t('zapi', 'trim() - Remove specified characters from the beginning and the end of a string'),
                'params' => $this->param1SecCount + [
                        'chars' => [
                            'C' => t('zapi', 'Chars'),
                            'T' => T_PRS_STR,
                            'A' => false
                        ]
                    ],
                'allowed_types' => $this->allowedTypesStr,
                'operators' => $this->operators
            ],
            'truncate' => [
                'types' => [PRS_FUNCTION_TYPE_MATH],
                'description' => t('zapi', 'truncate() - Truncates a value to decimal places'),
                'params' => $this->param1SecCount + [
                        'decimals' => [
                            'C' => t('zapi', 'Decimal places'),
                            'T' => T_PRS_STR,
                            'A' => true
                        ]
                    ],
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'varpop' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'varpop() - Population variance'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ],
            'varsamp' => [
                'types' => [PRS_FUNCTION_TYPE_AGGREGATE],
                'description' => t('zapi', 'varsamp() - Sample variance'),
                'params' => $this->param1SecCount,
                'allowed_types' => $this->allowedTypesNumeric,
                'operators' => $this->operators
            ]
        ];

        CArrayHelper::sort($this->functions, ['description']);
    }

    public function doAction(): Result
    {
        $expression_parser = new CExpressionParser(['usermacros' => true, 'lldmacros' => true]);
        $expression_validator = new CExpressionValidator([
            'usermacros' => true,
            'lldmacros' => true,
            'partial' => true
        ]);

        $itemid = $this->getInput('itemid', 0);
        $function = $this->getInput('function', 'last');
        $operator = $this->getInput('operator', '=');
        $param_type = $this->getInput('paramtype', PARAM_TYPE_TIME);
        $dstfld1 = $this->getInput('dstfld1');
        $expression = $this->getInput('expression', '');
        $params = $this->getInput('params', []);
        $value = $this->getInput('value', 0);

        $item = false;

        // Opening the popup when editing an expression in the trigger constructor.
        if (($dstfld1 === 'expr_temp' || $dstfld1 === 'recovery_expr_temp') && $expression !== '') {
            if ($expression_parser->parse($expression) == CParser::PARSE_SUCCESS) {
                $math_function_token = null;
                $hist_function_token = null;
                $function_token_index = null;
                $tokens = $expression_parser->getResult()->getTokens();

                foreach ($tokens as $index => $token) {
                    switch ($token['type']) {
                        case CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION:
                            $math_function_token = $token;
                            $function_token_index = $index;

                            foreach ($token['data']['parameters'] as $parameter) {
                                foreach ($parameter['data']['tokens'] as $parameter_token) {
                                    if ($parameter_token['type'] == CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION) {
                                        $hist_function_token = $parameter_token;
                                        break 2;
                                    }
                                }
                            }
                            break 2;

                        case CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION:
                            $hist_function_token = $token;
                            $function_token_index = $index;
                            break 2;
                    }
                }

                if ($function_token_index !== null) {
                    /*
                     * Try to find an operator and a value.
                     * The value and operator can be extracted only if they immediately follow the function.
                     */
                    $index = $function_token_index + 1;

                    if (array_key_exists($index, $tokens)
                        && $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_OPERATOR
                        && in_array($tokens[$index]['match'], $this->operators)) {
                        $operator = $tokens[$index]['match'];
                        $index++;

                        if (array_key_exists($index, $tokens)) {
                            if ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_NUMBER
                                || $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_MACRO
                                || $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_USER_MACRO
                                || $tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_LLD_MACRO) {
                                $value = $tokens[$index]['match'];
                            } elseif ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_STRING) {
                                $value = CExpressionParser::unquoteString($tokens[$index]['match']);
                            } elseif ($tokens[$index]['type'] == CExpressionParserResult::TOKEN_TYPE_OPERATOR
                                && array_key_exists($index + 1, $tokens)
                                && $tokens[$index + 1]['type'] == CExpressionParserResult::TOKEN_TYPE_NUMBER) {
                                $value = '-' . $tokens[$index + 1]['match'];
                            }
                        }
                    }

                    // Get function parameters.
                    $parameters = null;

                    if ($math_function_token) {
                        $function = $math_function_token['data']['function'];

                        if ($hist_function_token && $hist_function_token['data']['function'] === 'last') {
                            $parameters = $hist_function_token['data']['parameters'];
                        }
                    } else {
                        $function = $hist_function_token['data']['function'];
                        $parameters = $hist_function_token['data']['parameters'];
                    }

                    if ($parameters !== null) {
                        $host = $hist_function_token['data']['parameters'][0]['data']['host'];
                        $key = $hist_function_token['data']['parameters'][0]['data']['item'];

                        $itemSearch = new ItemSearch();
                        $itemSearch->is_all = true;
                        $provider = $itemSearch->search([
                            'output' => ['itemid', 'name', 'key_', 'value_type'],
                            'selectHosts' => ['name'],
                            'webitems' => true,
                            'filter' => [
                                'host' => $host,
                                'key_' => $key
                            ]
                        ]);
                        $items = $provider->getModels();

                        if (!$items) {
                            $itemSearch = new ItemPrototypeSearch();
                            $itemSearch->is_all = true;
                            $provider = $itemSearch->search([
                                'output' => ['itemid', 'name', 'key_', 'value_type'],
                                'selectHosts' => ['name'],
                                'filter' => [
                                    'host' => $host,
                                    'key_' => $key
                                ]
                            ]);
                            $items = $provider->getModels();
                        }

                        if (($item = reset($items)) === false) {
                            return $this->error(60750304, t('zapi', 'Unknown host item, no such item in selected host'));
                        }
                    }

                    $params = [];

                    if ($parameters !== null && array_key_exists(1, $parameters)) {
                        if ($function === "nodata" || $function === "fuzzytime") {
                            $params[] = ($parameters[1]['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED)
                                ? CHistFunctionParser::unquoteParam($parameters[1]['match'])
                                : $parameters[1]['match'];
                        } else {
                            if ($parameters[1]['type'] == CHistFunctionParser::PARAM_TYPE_PERIOD) {
                                $sec_num = $parameters[1]['data']['sec_num'];
                                if ($sec_num !== '' && $sec_num[0] === '#') {
                                    $params[] = substr($sec_num, 1);
                                    $param_type = PARAM_TYPE_COUNTS;
                                } else {
                                    $params[] = $sec_num;
                                    $param_type = PARAM_TYPE_TIME;
                                }
                                $params[] = $parameters[1]['data']['time_shift'];
                            } else {
                                $params[] = '';
                                $params[] = '';
                            }
                        }

                        for ($i = 2; $i < count($parameters); $i++) {
                            $parameter = $parameters[$i];
                            $params[] = $parameter['type'] == CHistFunctionParser::PARAM_TYPE_QUOTED
                                ? CHistFunctionParser::unquoteParam($parameter['match'])
                                : $parameter['match'];
                        }
                    }
                }
            }
        } // Opening an empty form or switching a function.
        else {
            $itemSearch = new ItemSearch();
            $itemSearch->is_all = true;
            $provider = $itemSearch->search([
                'output' => ['itemid', 'name', 'key_', 'value_type'],
                'selectHosts' => ['host', 'name'],
                'itemids' => $itemid,
                'webitems' => true,
                'filter' => ['flags' => null]
            ]);
            $item = $provider->getModels();
            $item = reset($item);
        }

        if ($item) {
            $itemid = $item['itemid'];
            $item_value_type = $item['value_type'];
            $item_key = $item['key_'];
            $item_host_data = reset($item['hosts']);
            $description = $item_host_data['name'] . NAME_DELIMITER . $item['name'];
        } else {
            $item_key = '';
            $description = '';
            $item_value_type = null;
        }

        if ($param_type === null && array_key_exists($function, $this->functions)
            && array_key_exists('params', $this->functions[$function])
            && array_key_exists('M', $this->functions[$function]['params'])) {
            $param_type = is_array($this->functions[$function]['params']['M'])
                ? reset($this->functions[$function]['params']['M'])
                : $this->functions[$function]['params']['M'];
        } elseif ($param_type === null) {
            $param_type = PARAM_TYPE_TIME;
        }

        // Functions with optional #num and time shift parameters.
        $count_functions = [
            'acos', 'ascii', 'asin', 'atan', 'atan2', 'between', 'bitand', 'bitlength', 'bitlshift', 'bitnot', 'bitor',
            'bitrshift', 'bitxor', 'bytelength', 'cbrt', 'ceil', 'char', 'concat', 'cos', 'cosh', 'cot', 'degrees', 'exp',
            'expm1', 'floor', 'in', 'insert', 'last', 'left', 'length', 'log', 'log10', 'ltrim', 'mid', 'mod', 'power',
            'radians', 'rate', 'repeat', 'replace', 'right', 'round', 'rtrim', 'signum', 'sin', 'sinh', 'sqrt', 'tan',
            'trim', 'truncate'
        ];

        $data = [
            'parent_discoveryid' => $this->getInput('parent_discoveryid', ''),
            'dstfrm' => $this->getInput('dstfrm'),
            'dstfld1' => $dstfld1,
            'context' => $this->getInput('context'),
            'itemid' => $itemid,
            'value' => $value,
            'params' => $params,
            'paramtype' => $param_type,
            'item_description' => $description,
            'item_required' => !in_array($function, array_merge($this->getStandaloneFunctions(), $this->getFunctionsConstants())),
            'functions' => $this->functions,
            'function' => $function,
            'function_type' => reset($this->functions[$function]['types']),
            'operator' => $operator,
            'item_key' => $item_key,
            'itemValueType' => $item_value_type,
            'selectedFunction' => null,
            'groupid' => $this->getInput('groupid', 0),
            'hostid' => $this->getInput('hostid', 0),
            'function_types' => TriggerHelper::functionTypes(),
            'count_functions' => $count_functions
        ];

        // Check if submitted function is usable with selected item.
        foreach ($data['functions'] as $id => $f) {
            if (($data['itemValueType'] === null || array_key_exists($item_value_type, $f['allowed_types']))
                && $id === $function) {
                $data['selectedFunction'] = $id;
                break;
            }
        }

        if ($data['selectedFunction'] === null) {
            $data['selectedFunction'] = 'last';
            $data['function'] = 'last';
            $data['function_type'] = PRS_FUNCTION_TYPE_HISTORY;
        }

        // Remove functions that not correspond to chosen item.
        foreach ($data['functions'] as $id => $f) {
            if ($data['itemValueType'] !== null && !array_key_exists($data['itemValueType'], $f['allowed_types'])) {
                unset($data['functions'][$id]);

                // Take first available function from list.
                if ($id === $data['function']) {
                    $data['function'] = key($data['functions']);
                    $data['function_type'] = reset($data['functions'][$data['function']]['types']);
                    $data['operator'] = reset($data['functions'][$data['function']]['operators']);
                }
            }
        }

        // Create and validate trigger expression before inserting it into textarea field.
        if ($this->getInput('add', false)) {
            try {
                if (in_array($function, $this->getFunctionsConstants())) {
                    $data['expression'] = sprintf('%s()', $function);
                } elseif (in_array($function, $this->getStandaloneFunctions())) {
                    $data['expression'] = sprintf('%s()%s%s', $function, $operator,
                        CExpressionParser::quoteString($data['value'])
                    );
                } elseif ($data['item_description']) {
                    // Quote function string parameters.
                    $quote_params = [
                        'algorithm',
                        'chars',
                        'fit',
                        'mode',
                        'o',
                        'pattern',
                        'replace',
                        'season_unit',
                        'string',
                        'v'
                    ];
                    $quote_params = array_intersect_key($data['params'], array_fill_keys($quote_params, ''));
                    $quote_params = array_filter($quote_params, 'strlen');

                    foreach ($quote_params as $param_key => $param) {
                        $data['params'][$param_key] = $this->quoteFunctionParam($param, true);
                    }

                    // Combine sec|#num and <time_shift|period_shift> parameters into one.
                    if (array_key_exists('last', $data['params'])) {
                        if ($data['paramtype'] == PARAM_TYPE_COUNTS && prs_is_int($data['params']['last'])) {
                            $data['params']['last'] = '#' . $data['params']['last'];
                        }
                    } else {
                        $data['params']['last'] = '';
                    }

                    if (array_key_exists('shift', $data['params']) && $data['params']['shift'] !== '') {
                        $data['params']['last'] .= ':' . $data['params']['shift'];
                    } elseif (array_key_exists('period_shift', $data['params'])
                        && $data['params']['period_shift'] !== '') {
                        $data['params']['last'] .= ':' . $data['params']['period_shift'];
                    }
                    unset($data['params']['shift'], $data['params']['period_shift']);

                    // Functions where item is wrapped in last() like func(last(/host/item)).
                    $last_functions = [
                        'abs', 'acos', 'ascii', 'asin', 'atan', 'atan2', 'between', 'bitand', 'bitlength', 'bitlshift',
                        'bitnot', 'bitor', 'bitrshift', 'bitxor', 'bytelength', 'cbrt', 'ceil', 'char', 'concat', 'cos',
                        'cosh', 'cot', 'degrees', 'exp', 'expm1', 'floor', 'in', 'insert', 'left', 'length', 'log', 'log10',
                        'ltrim', 'mid', 'mod', 'power', 'radians', 'repeat', 'replace', 'right', 'round', 'signum',
                        'sin', 'sinh', 'sqrt', 'tan', 'trim', 'truncate'
                    ];

                    if (in_array($function, $last_functions)) {
                        $last_params = $data['params']['last'];
                        unset($data['params']['last']);
                        $fn_params = rtrim(implode(',', $data['params']), ',');

                        $data['expression'] = sprintf('%s(last(/%s/%s%s)%s)%s%s',
                            $function,
                            $item_host_data['host'],
                            $data['item_key'],
                            ($last_params === '') ? '' : ',' . $last_params,
                            ($fn_params === '') ? '' : ',' . $fn_params,
                            $operator,
                            CExpressionParser::quoteString($data['value'])
                        );
                    } else {
                        $fn_params = rtrim(implode(',', $data['params']), ',');

                        $data['expression'] = sprintf('%s(/%s/%s%s)%s%s',
                            $function,
                            $item_host_data['host'],
                            $data['item_key'],
                            ($fn_params === '') ? '' : ',' . $fn_params,
                            $operator,
                            CExpressionParser::quoteString($data['value'])
                        );
                    }
                } else {
                    return $this->error(60750304, t('zapi', 'Item not selected'));
                }

                if (array_key_exists('expression', $data)) {
                    // Parse and validate trigger expression.
                    if ($expression_parser->parse($data['expression']) == CParser::PARSE_SUCCESS) {
                        if (!$expression_validator->validate($expression_parser->getResult()->getTokens())) {
                            return $this->error(60750304, t('zapi', 'Invalid condition: {error}', ['error' => $expression_validator->getError()]));
                        }
                    } else {
                        return $this->error(60750304, $expression_parser->getError());
                    }
                }
            } catch (\Exception $e) {
                return $this->error(60750304, $e->getMessage());
            }
            $output = [
                'expression' => $data['expression'],
                'dstfld1' => $data['dstfld1'],
                'dstfrm' => $data['dstfrm']
            ];
            return $this->success($output);
        } else {
            return $this->success($data);
        }
    }

    /**
     * Return list of functions that can be used without /host/key reference.
     *
     * @return array
     */
    protected function getStandaloneFunctions(): array
    {
        return ['date', 'dayofmonth', 'dayofweek', 'time', 'now'];
    }

    /**
     * Returns a list of functions that return a constant or random number.
     *
     * @return array
     */
    protected function getFunctionsConstants(): array
    {
        return ['e', 'pi', 'rand'];
    }

    /**
     * Quoting $param if it contains special characters.
     *
     * @param string $param
     * @param bool $forced
     *
     * @return string
     */
    protected function quoteFunctionParam($param, $forced = false)
    {
        if (!$forced) {
            if (!isset($param[0]) || ($param[0] != '"' && false === strpbrk($param, ',)'))) {
                return $param;
            }
        }

        return '"' . str_replace('"', '\\"', $param) . '"';
    }


    protected function getInput($var, $default = null)
    {
        if ($default === null) {
            return $this->input[$var];
        } else {
            return array_key_exists($var, $this->input) ? $this->input[$var] : $default;
        }
    }
}

