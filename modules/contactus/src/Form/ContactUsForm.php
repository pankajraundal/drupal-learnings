<?php

namespace Drupal\contactus\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the SimpleForm form controller.
 *
 * This example demonstrates a simple form with a single text input element. We
 * extend FormBase which is the simplest form base class used in Drupal.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class ContactUsForm extends FormBase
{

   /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contactus_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {

    // Title
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
    ];

    // Preferred date to contact.
    $form['date_to_contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('date'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    
    $form['#attached']['library'][] = 'contactus/contact_us_lib';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
  }
}
