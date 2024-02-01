<?php

namespace Drupal\mclaim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mclaim\Form\SubmitClaimsForm;

class ClaimController extends ControllerBase {

  public function viewClaimsPage() {
    return [
      '#theme' => 'mclaim_tabs_page',
      '#content' => $this->viewClaimsContent(),
      '#tabs' => [
        '/mclaim/view-claims' => 'View Claims',
        '/mclaim/submit-forms' => 'Submit Forms',
      ],
      '#attached' => [
        'library' => ['mclaim/tabs'],
      ],
    ];
  }

  public function submitFormsPage() {
    return [
      '#theme' => 'mclaim_tabs_page',
      '#content' => $this->submitFormsContent(),
      '#tabs' => [
        '/mclaim/view-claims' => 'View Claims',
        '/mclaim/submit-forms' => 'Submit Forms',
      ],
      '#attached' => [
        'library' => ['mclaim/tabs'],
      ],
    ];
  }

  protected function viewClaimsContent() {
    // Add content for the "View Claims" tab.
    return [
      '#markup' => '<p>This is the content for View Claims tab.</p>',
    ];
  }

  protected function submitFormsContent() {
    $form = $this->formBuilder()->getForm(SubmitClaimsForm::class);
    return $form;
  }

}
