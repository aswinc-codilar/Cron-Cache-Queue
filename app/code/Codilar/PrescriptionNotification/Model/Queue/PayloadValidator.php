<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model\Queue;

use Codilar\PrescriptionNotification\Model\FileStorage;
use Magento\Framework\Exception\ValidatorException;

/**
 * Validates {"customer_id":int,"order_id":int,"prescription_file":string}.
 */
class PayloadValidator
{
    /**
     * @return array{customer_id: int, order_id: int, prescription_file: string}
     * @throws ValidatorException
     */
    public function validate(string $message): array
    {
        try {
            $data = json_decode($message, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidatorException(__('Payload is not valid JSON: %1', $e->getMessage()));
        }
        if (!is_array($data)) {
            throw new ValidatorException(__('Payload must be a JSON object.'));
        }

        $errors = [];
        foreach (['customer_id', 'order_id'] as $key) {
            if (!isset($data[$key]) || filter_var($data[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $errors[] = sprintf('"%s" must be a positive integer', $key);
            }
        }

        $file = $data['prescription_file'] ?? null;
        if (!is_string($file) || trim($file) === '') {
            $errors[] = '"prescription_file" must be a non-empty string';
        } elseif (strlen($file) > 255 || basename($file) !== $file || str_starts_with($file, '.')) {
            $errors[] = '"prescription_file" must be a plain file name';
        } elseif (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), FileStorage::ALLOWED_EXTENSIONS, true)) {
            $errors[] = '"prescription_file" has a disallowed extension';
        }

        if ($errors) {
            throw new ValidatorException(__('Invalid payload: %1', implode('; ', $errors)));
        }

        return [
            'customer_id' => (int)$data['customer_id'],
            'order_id' => (int)$data['order_id'],
            'prescription_file' => $file,
        ];
    }
}
