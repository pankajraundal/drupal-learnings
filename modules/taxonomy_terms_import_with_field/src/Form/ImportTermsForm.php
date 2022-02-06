<?php

namespace Drupal\taxonomy_terms_import_with_field\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use \Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Database\Database;

/**
 * Implements the Import Terms form controller.
 *
 * This service is form with a textfile input field which allow user. Which
 * allow user to submit csv post that we can process it and import the fields
 * 
 * @see \Drupal\Core\Form\FormBase
 */

class ImportTermsForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'upload_terms_with_field';
    }
    /**
     * Build the simple form.
     *
     * A build form method constructs an array that defines how markup and
     * other form elements are included in an HTML form.
     *
     * @param array $form
     *   Default form array structure.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   Object containing current form state.
     *
     * @return array
     *   The render array defining the elements of the form.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $validators = array(
            'file_validate_extensions' => array('csv'),
        );

        $vocabularies = Vocabulary::loadMultiple();
        $vocabulariesList = [];
        foreach ($vocabularies as $vid => $vocablary) {
            $vocabulariesList[$vid] = $vocablary->get('name');
        }
        $form['field_vocabulary_name'] = [
            '#type' => 'select',
            '#title' => $this->t('Vocabulary name'),
            '#required' => TRUE,
            '#options' => $vocabulariesList,
            '#attributes' => [
                'class' => ['vocab-name-select'],
            ],
            '#description' => t('Select vocabulary!'),
        ];
        $form['taxonomy_file'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Import file'),
            '#required' => TRUE,
            '#upload_validators'  =>  $validators,
            '#upload_location' => 'public://taxonomy_import_files/',
            '#description' => $this->t('Upload a file to Import taxonomy!'),
        ];
        $form['actions']['#type'] = 'actions';
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Import'),
            '#button_type' => 'primary',
        ];
        return $form;
    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {

        // Get vocabulary name.
        $vocabulary = $form_state->getValue('field_vocabulary_name');

        // Get uploaded file path.
        $file = \Drupal::entityTypeManager()->getStorage('file')
            ->load($form_state->getValue('taxonomy_file')[0]);
        $file_path = $file->get('uri')->value;
        // Call method to import all terms.
        $this->import_taxonomy_term($vocabulary, $file_path);
    }

    /**
     * Build the function which create taxonomy term.
     *
     * A function which takes vocabulary and uploaded file as parameters. And
     * import the taxonomies of selected vocabulary. 
     *
     * @param string $vocabulary
     *   Vocabulry selected on the form.
     * @param string $file_path
     *   File path uploaded on the form.
     *
     */
    protected function import_taxonomy_term(string $vocabulary,  string $file_path)
    {
        global $base_url;
        $location =  $file_path;
        $vid = $vocabulary;
        if (($handle = fopen($location, "r")) !== FALSE) {
            while (($data = fgetcsv($handle)) !== FALSE) {
                
                $termid = 0;
                $term_id = 0;
                $termid = Database::getConnection()->query('SELECT n.tid FROM {taxonomy_term_field_data} n WHERE n.name  = :uid AND n.vid  = :vid', [':uid' => $data[0], ':vid' => $vid]);
                foreach ($termid as $val) {
                    // Get tid.
                    $term_id = $val->tid;
                }
                // Make sure term is not already exist.
                if (empty($term_id)) {
                    // Create  new term.
                   $term = Term::create([
                        'vid' => $vid,
                        'name' => $data[0],
                        'parent' => '',
                        'description' =>  $data[1],
                        'field_name' => $data[2],
                        'field_distributer_email' => $data[3],
                        'field_distributer_number' => $data[4],
                    ])->save();
                }
                // Else to update existing term, for now its not needed.
            }
            fclose($handle);
        }
        $url = $base_url . "/distributer-list";
        header('Location:' . $url);exit;
    }
}
