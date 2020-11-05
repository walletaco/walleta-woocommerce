<?php
if (!defined('ABSPATH')) {
    exit;
}

class Walleta_Http_Response
{
    /**
     * @var int
     */
    protected $code;

    /**
     * @var string
     */
    protected $data;

    /**
     * Response constructor.
     *
     * @param int $code
     * @param string $body
     */
    public function __construct($code, $body)
    {
        $this->code = (int)$code;
        $this->data = json_decode($body, true);
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->code === 200;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->code;
    }

    /**
     * @param string|null $key
     * @return mixed
     */
    public function getData($key = null)
    {
        if ($key === null) {
            return $this->data;
        }

        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getErrorType()
    {
        return !$this->isSuccess() ? $this->getData('type') : null;
    }

    /**
     * @return string|null
     */
    public function getErrorMessage()
    {
        return !$this->isSuccess() ? $this->getData('message') : null;
    }

    /**
     * @return array
     */
    public function getValidationErrors()
    {
        $invalidFields = $this->getData('invalid_fields');
        $errors = [];

        if ($invalidFields) {
            foreach ($invalidFields as $invalidField) {
                $errors[] = sprintf('%s: %s', $invalidField['field'], $invalidField['message']);
            }
        }

        return $errors;
    }
}