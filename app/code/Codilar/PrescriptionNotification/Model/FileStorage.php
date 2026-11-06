<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;

/**
 * Stores prescriptions under var/prescription/{customer_id}/ — outside pub/, so medical
 * documents are never directly downloadable over HTTP.
 */
class FileStorage
{
    public const BASE_DIR = 'prescription';
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly UploaderFactory $uploaderFactory
    ) {
    }

    /**
     * @return array{file: string, name: string}
     * @throws LocalizedException
     */
    public function saveUploadedFile(string $fileId, int $customerId): array
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Please choose a prescription file to upload.'));
        }

        $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $uploader->setAllowCreateFolders(true);

        if (!$uploader->checkAllowedExtension($uploader->getFileExtension())) {
            throw new LocalizedException(__('Only PDF, JPG and PNG files are allowed.'));
        }
        if (!$uploader->checkMimeType(self::ALLOWED_MIME_TYPES)) {
            throw new LocalizedException(__('The file content does not match an allowed type (PDF, JPG, PNG).'));
        }

        $result = $uploader->save($this->getVarDirectory()->getAbsolutePath($this->getRelativeDir($customerId)));
        if (!$result || empty($result['file'])) {
            throw new LocalizedException(__('The prescription could not be saved. Please try again.'));
        }

        return ['file' => (string)$result['file'], 'name' => (string)($result['name'] ?? $result['file'])];
    }

    public function exists(int $customerId, string $file): bool
    {
        // basename() guards against path traversal even if the payload validator is bypassed
        $path = $this->getRelativeDir($customerId) . '/' . basename($file);
        return $this->getVarDirectory()->isFile($path);
    }

    private function getRelativeDir(int $customerId): string
    {
        return self::BASE_DIR . '/' . $customerId;
    }

    private function getVarDirectory(): Filesystem\Directory\WriteInterface
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }
}
