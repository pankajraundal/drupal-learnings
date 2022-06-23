<?php

namespace Drupal\custom_user\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\State\State;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;


/**
 * Calleo Form for contact_us page.
 */
class CustomUserAddForm extends FormBase {
  

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_user_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['input_required_fields'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Field with * are required fields') . '</p>',
    ];
    // First Name.
    $form['first_name'] = [
      '#title' => $this->t('First Name'),
      '#type' => 'textfield',
      '#maxlength' => 40,
      '#required' => true,
      
    ];

    // Last Name.
    $form['last_name'] = [
      '#title' => $this->t('Last Name'),
      '#type' => 'textfield',
      '#maxlength' => 40,
      '#required' => true,
      
    ];

    // Age.
    $form['age'] = [
      '#title' => $this->t('Age'),
      '#type' => 'textfield',
      '#maxlength' => 3,
      
    ];

    // Mobile Number.
    $form['mobile_number'] = [
      '#title' => $this->t('Mobile Number'),
      '#type' => 'textfield',
      '#maxlength' => 10,
      '#required' => true,
    ];

    // Birth Date.
    $form['birth_date'] = [
      '#title' => $this->t('Birth Date'),
      '#type' => 'textfield',
      
    ];

    $form['#attached']['library'][] = 'custom_user/adduser_lib';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
    ];
    
    return $form;
  }

  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
   // Mobile number validation.
   $mobile_number = $form_state->getValue('mobile_number');
   if(!empty($mobile_number)) {
      // Check if all numbers are entered.
      if(!is_numeric($mobile_number)) {
        $form_state->setErrorByName('mobile_number', $this->t('Please enter valid mobile number.'));
      }
      // Check if mobile number is not greater than 10.
      if(strlen($mobile_number) != 10) {
        $form_state->setErrorByName('mobile_number', $this->t('Please enter valid 10 digit mobile number.'));
      }
    }
    // Age number validation.
    $age = $form_state->getValue('age');
    if(!empty($age)) {
      if(!is_numeric($age)) {
        $form_state->setErrorByName('age', $this->t('Please enter valid age.'));
      }
      // Check if mobile number is not greater than 10.
      if(strlen($age) > 3) {
        $form_state->setErrorByName('age', $this->t('Please enter valid age.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $first_name = $form_state->getValue('first_name');
    $last_name = $form_state->getValue('last_name');
    $age = $form_state->getValue('age');
    $mobile_number = $form_state->getValue('mobile_number');
    $birth_date = $form_state->getValue('birth_date');
    $date = str_replace('/', '-', $birth_date);
    $birth_date_timestamp = date('Y-m-d', strtotime($birth_date));

    $connection = Database::getConnection();
    try{
    $result = $connection->insert('custom_user')
    ->fields([
      'first_name' => $form_state->getValue('first_name'),
      'last_name' => $form_state->getValue('last_name'),
      'age' => $form_state->getValue('age'),
      'mobile_number' => $form_state->getValue('mobile_number'),
      'birth_date' => $birth_date_timestamp ,
    ])
    ->execute();
    
      \Drupal::messenger()->addMessage('User added successfully');
    } catch(Exception $ex) {
      \Drupal::logger('custom_user')->error($ex->getMessage());
    }
    
  }
}
