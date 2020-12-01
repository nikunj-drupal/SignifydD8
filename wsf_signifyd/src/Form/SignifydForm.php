<?php

namespace Drupal\wsf_signifyd\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Defines a form that configures forms module settings.
 */
class SignifydForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'wsf_signifyd_config';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
          'wsf_signifyd_config.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('wsf_signifyd_config.settings');
        $form['signifyd_api_key'] = [
          '#type' => 'textfield',
          '#title' => t('API Key'),
          '#default_value' => \Drupal::state()->get('signifyd_api_key'),
          '#size' => 45,
          '#maxlength' => 255,
        ];
        $form['signifyd_api_url'] = [
          '#type' => 'textfield',
          '#title' => t('API URL'),
          '#default_value' => \Drupal::state()->get('signifyd_api_url'),
          '#size' => 45,
          '#maxlength' => 255,
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {   
        $values = $form_state->getValues();
        $signifyd_api_key = $form_state->getValue('signifyd_api_key');
        $signifyd_api_url = $form_state->getValue('signifyd_api_url');
        \Drupal::state()->set('signifyd_api_key', $signifyd_api_key);
        \Drupal::state()->set('signifyd_api_url', $signifyd_api_url);
        $this->config('wsf_signifyd_config.settings')
          ->set('consumer_key', $consumer_key)
          ->set('consumer_secret', $consumer_secret)
          ->save();
        parent::submitForm($form, $form_state);
    }
}
