package tn.unidar.desktop.utils;

import tn.unidar.desktop.services.LanguageManager;
import java.util.Locale;

/**
 * Minimal i18n helper.
 * Now wraps the LanguageManager for reactive global translations.
 */
public final class I18n {

    /** Translate using the current app language. */
    public static String t(String key) {
        return LanguageManager.getInstance().getString(key);
    }

    /** Translate for an explicit language code ("en", "fr", "ar"). */
    public static String t(String key, String lang) {
        // If an explicit lang is requested, we could load a temp bundle, 
        // but for Unidar's global sync, we use the current manager state.
        return LanguageManager.getInstance().getString(key);
    }
}
