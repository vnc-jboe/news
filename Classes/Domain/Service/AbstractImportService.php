<?php

/*
 * This file is part of the "news" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace GeorgRinger\News\Domain\Service;

use GeorgRinger\News\Domain\Model\Dto\EmConfiguration;
use GeorgRinger\News\Domain\Repository\CategoryRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\Index\FileIndexRepository;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class AbstractImportService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const UPLOAD_PATH = 'uploads/tx_news/';

    protected PersistenceManager $persistenceManager;
    protected array $postPersistQueue = [];
    protected EmConfiguration $emSettings;
    protected ?Folder $importFolder = null;
    protected EventDispatcherInterface $eventDispatcher;
    protected CategoryRepository $categoryRepository;

    /**
     * @param EmConfiguration $emSettings
     */
    public function __construct(
        PersistenceManager $persistenceManager,
        CategoryRepository $categoryRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->emSettings = GeneralUtility::makeInstance(EmConfiguration::class);
        $this->persistenceManager = $persistenceManager;
        $this->categoryRepository = $categoryRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Compares 2 files by using its filesize
     *
     * @param string $file1 Absolute path and filename to file1
     * @param string $file2 Absolute path and filename to file2
     */
    protected function filesAreEqual($file1, $file2): bool
    {
        return filesize($file1) === filesize($file2);
    }

    /**
     * Find a existing file by its hash
     *
     * @param string $hash
     * @return File|ProcessedFile|null
     */
    protected function findFileByHash($hash)
    {
        $file = null;

        $files = $this->getFileIndexRepository()->findByContentHash($hash);
        if (count($files)) {
            foreach ($files as $fileInfo) {
                if ($fileInfo['storage'] > 0) {
                    $file = $this->getResourceFactory()->getFileObjectByStorageAndIdentifier(
                        $fileInfo['storage'],
                        $fileInfo['identifier']
                    );
                    break;
                }
            }
        }
        if ($file === null) {
            return null;
        }
        if (!$file->exists()) {
            return null;
        }
        return $file;
    }

    /**
     * Get import Folder
     *
     * TODO: catch exception when storage/folder does not exist and return readable message to the user
     */
    protected function getImportFolder(): Folder
    {
        if ($this->importFolder === null) {
            $this->importFolder = $this->getResourceFactory()->getFolderObjectFromCombinedIdentifier($this->emSettings->getStorageUidImporter() . ':' . $this->emSettings->getResourceFolderImporter());
        }
        return $this->importFolder;
    }

    protected function getFileIndexRepository(): FileIndexRepository
    {
        return GeneralUtility::makeInstance(FileIndexRepository::class);
    }

    protected function getResourceStorage(): ResourceStorage
    {
        return $this->getResourceFactory()->getStorageObject($this->emSettings->getStorageUidImporter());
    }

    protected function getResourceFactory(): ResourceFactory
    {
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        return $resourceFactory;
    }

    protected function convertTimestampToDateTime(int $timestamp): ?\DateTime
    {
        if ($timestamp < 1) {
            return null;
        }

        try {
            return new \DateTime(date('c', $timestamp));
        } catch (\Exception) {
            return null;
        }
    }
}
