<?php

namespace Drupal\mclaim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mclaim\Form\SubmitClaimsForm;
use Drupal\mclaim\Form\ClaimFilterForm;
use Drupal\Core\Http\ClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClaimController extends ControllerBase {

  protected $httpClientFactory;

  protected $tabs = [
    '/mclaim/view-claims' => 'View Claims',
    '/mclaim/submit-forms' => 'Submit Forms',
  ];

  public function __construct(ClientFactory $httpClientFactory) {
    $this->httpClientFactory = $httpClientFactory;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client_factory')
    );
  }

  public function viewClaimsPage() {
    return [
      '#theme' => 'mclaim_view_page',
      '#content' => $this->viewClaimsContent(),
      '#form' => $this->claimFilterForm(),
      '#tabs' => $this->tabs,
      '#attached' => [
        'library' => ['mclaim/tabs'],
      ],
      'cache' => [
        'max-age' => 0,
      ],
    ];
  }

  public function submitFormsPage() {
    return [
      '#theme' => 'mclaim_submit_form',
      '#content' => $this->submitFormsContent(),
      '#tabs' => $this->tabs,
      '#attached' => [
        'library' => ['mclaim/tabs'],
      ],
    ];
  }

  protected function claimFilterForm() {
    return $form =  $this->formBuilder()->getForm(ClaimFilterForm::class);
  }

  protected function viewClaimsContent() {
    // Access all values from the query string.
    $values = \Drupal::request()->query->all();

    // Check the protocol.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    // Create the URL.
    $url = $protocol . $_SERVER['HTTP_HOST'] . '/api/get-claims';
    $response = \Drupal::httpClient()->post($url, [
      'json' => $values,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
    ]);
    $data = [];

    if ($response->getStatusCode() == 200) {
      $data = json_decode($response->getBody(), TRUE);
    }
    return $data;
  }

  protected function submitFormsContent() {
    $form = $this->formBuilder()->getForm(SubmitClaimsForm::class);
    return $form;
  }

}
