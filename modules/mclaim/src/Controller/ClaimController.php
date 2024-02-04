<?php

namespace Drupal\mclaim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mclaim\Form\SubmitClaimsForm;
use Drupal\mclaim\Form\ClaimFilterForm;
use Drupal\Core\Http\ClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClaimController extends ControllerBase {

  /**
   * @var \Drupal\Core\Http\ClientFactory
   *   The HTTP client factory.
   */
  protected $httpClientFactory;

  /**
   * The tabs array.
   *
   * @var array
   */
  protected $tabs = [
    '/mclaim/view-claims' => 'View Claims',
    '/mclaim/submit-forms' => 'Submit Forms',
  ];

  /**
   * ClaimController constructor.
   *
   * @param ClientFactory $httpClientFactory The HTTP client factory.
   */
  public function __construct(ClientFactory $httpClientFactory) {
    $this->httpClientFactory = $httpClientFactory;
  }

  /**
   * Creates a new instance of the ClaimController class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   *
   * @return \Drupal\mclaim\Controller\ClaimController
   *   The ClaimController instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client_factory')
    );
  }

  /**
   * Displays the view claims page.
   *
   * @return array
   */
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

  /**
   * Page to submit the claims.
   *
   * @return array
   */
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

  /**
   * Generates the claim filter form.
   *
   * @return array
   *   The claim filter form.
   */
  protected function claimFilterForm() {
    return $form =  $this->formBuilder()->getForm(ClaimFilterForm::class);
  }

  /**
   * Retrieves and displays the claims content.
   *
   * @return array
   *   The response array containing the claims content.
   */
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

  /**
   * Submits the forms content.
   *
   * @return array
   *   The sunmit claim form.
   */
  protected function submitFormsContent() {
    return $form = $this->formBuilder()->getForm(SubmitClaimsForm::class);
  }

}
