<?php

namespace Drupal\tmgmt_contentapi;

use \Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\File\FileSystem;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class used to log journey of a job in textfile.
 */
class JobJourneyLog {
  /**
   * Constant to contains folder name.
   */
  CONST LOG_FOLDER_NAME = "/tmgmt_contentapi/LioxProgressLogs";

   /**
   * Constant to contains files suffix name.
   */
  const FILE_NAME_SUFFIX = "_cron_progress_log.txt";

  /**
   * 
   * Function to check if log folder exit, else create it.
   * @return string
   */
  public function getLogFolder() {
    // TODO: Ideally this need to use via service using dependency injection
    // Check if folder already exist on mentioned path.
    $path = \Drupal::service('file_system')->realpath('public://');
    $dirPath = $path . self::LOG_FOLDER_NAME;
    if (file_exists($dirPath)) {
      return $dirPath;
    } else {
        // If folder not exist create one and share return path
        mkdir($dirPath, 0755);
        return $dirPath;
    }
  }

  /**
   * 
   * Function to check if log file, else create it.
   * @return string
   */
  public function getLogFile(string $jobId = '') {
    // Get folder where we need to create path.
    $folderPath = $this->getLogFolder();
    // File path in which we are going to store our logs
    if($jobId != '') {
      $log_file_name = $jobId. SELF::FILE_NAME_SUFFIX;
    } else {
      $log_file_name = date("Y_m_d"). SELF::FILE_NAME_SUFFIX;
    }
    $fileFullPath = $folderPath .'/'. $log_file_name;
    // return file path if alreay exist.
    if (file_exists($fileFullPath)) {
      return $fileFullPath;
    } else {
      // Create file if not exist
      $fh = fopen($fileFullPath, 'w');
      if ($fh) {
        return $fileFullPath;
      } else {
        return "Failed to create file";
      }
    }
  }

  /**
   * Function to process array and convert it to writable string
   * @param array $arrayValue
   * @return string
   */
  public function preProcessArrayToLog(array $arrayValue) {
    $arrayToJson = json_encode($arrayValue, JSON_PRETTY_PRINT);
    return $arrayToJson;
  }
  /**
   * Summary of tmgmt_contentapi_log_to_file
   * @return boolean
   */
  public function writeLogToFile(string $jobId = '', string $message, array $logVar = []) {
    // TODO: Ideally this need to use via service using dependency injection
    $current_time = \Drupal::time()->getCurrentTime();
    $log_time = \Drupal::service('date.formatter')->format($current_time, 'custom', 'd-m-Y:H:i:s');
    $finalFile = $this->getLogFile($jobId);
    // Process array to log it in text format.
    $logVar = $this->preProcessArrayToLog($logVar);
    // Create a message to log into the file.
    // $txt = t('@log_time - @jobId - @message - @logVar',
    // [
    //   '@log_time' => $log_time, 
    //   '@jobId' => $jobId, 
    //   '@logVar' => $logVar,
    //   '@message' => $message
    // ]);
    $txt = t($log_time . "-" . $jobId . "-" . $message . "-" . $logVar);
    $myfile = file_put_contents($finalFile, $txt . PHP_EOL, FILE_APPEND | LOCK_EX);
    return $myfile; 
  }
}