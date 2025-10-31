<?php
declare(strict_types=1);

namespace App\Controllers;

use App\ServicesTranslationService;

/**
 * LocaleController
 * 
 * Handles locale/language switching
 */
class LocaleController extends BaseController
{
    /**
     * Switch locale
     */
    public function switch(): void
    {
        $locale = $this->getPost('locale') ?? $this->getQuery('locale');
        
        if (!$locale) {
            $this->json(['error' => 'Locale parameter required'], 400);
        }
        
        $availableLocales = array_keys(TranslationService::getAvailableLocales());
        
        if (!in_array($locale, $availableLocales)) {
            $this->json(['error' => 'Invalid locale'], 400);
        }
        
        TranslationService::setLocale($locale);
        
        if ($this->isHtmx()) {
            $this->json([
                'success' => true,
                'message' => 'Language changed successfully'
            ]);
        }
        
        // Redirect back or to dashboard
        $redirect = $this->getQuery('redirect', '/dashboard');
        $this->redirect($redirect);
    }
}
