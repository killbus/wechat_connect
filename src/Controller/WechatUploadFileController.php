<?php

namespace Drupal\wechat_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class WechatUploadFileController.
 */
class WechatUploadFileController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Constructs a new WechatUploadFileController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Do.
   *
   * @return string
   *   Return Hello string.
   */
  public function do() {
    $request = \Drupal::request();
    if (strpos($request->headers->get('content-type'), 'multipart/form-data;') !== 0) {
      $res = new JsonResponse();
      $res->setStatusCode(400, 'must submit multipart/form-data');
      return $res;
    }

    $directory = file_default_scheme() . '://wechat_upload_files';
    try {
      \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    }
    catch (\Drupal\Core\File\Exception\FileException $e) {
      // Log or set message or doing something else.
    }

    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */
    $uploadedFile = $request->files->get('file');
    $fileName = $uploadedFile->getClientOriginalName();
    if (empty($fileName)) {
      throw new HttpException(400, 'File name not found');
    }

    $path = $uploadedFile->getPathName();
    $fileData = file_get_contents($path, FILE_USE_INCLUDE_PATH);


    $file = file_save_data($fileData, $directory.'/'.$fileName, FileSystemInterface::EXISTS_RENAME);

    $mime = $_FILES['image']['type'];

    $response['file'] = $file->createFileUrl();
    $response['data'] = $_POST;

    \Drupal::moduleHandler()->invokeAll('wechat_upload_file', [$file, $_POST]);

    return new JsonResponse($response);
  }

}
