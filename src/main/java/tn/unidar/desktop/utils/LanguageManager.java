package tn.unidar.desktop.utils;

import javafx.beans.property.ObjectProperty;
import javafx.beans.property.SimpleObjectProperty;
import java.util.Locale;
import java.util.ResourceBundle;

/**
 * Singleton to manage the application's current language and notify all components.
 */
public class LanguageManager {
    private static LanguageManager instance;
    private final ObjectProperty<Locale> localeProperty = new SimpleObjectProperty<>(new Locale("fr"));
    private ResourceBundle bundle;

    private LanguageManager() {
        updateBundle(localeProperty.get());
        localeProperty.addListener((obs, oldV, newV) -> updateBundle(newV));
    }

    public static LanguageManager getInstance() {
        if (instance == null) {
            instance = new LanguageManager();
        }
        return instance;
    }

    public ObjectProperty<Locale> localeProperty() {
        return localeProperty;
    }

    public void setLocale(Locale locale) {
        localeProperty.set(locale);
    }

    public Locale getLocale() {
        return localeProperty.get();
    }

    private void updateBundle(Locale locale) {
        try {
            bundle = ResourceBundle.getBundle("i18n.messages", locale);
        } catch (Exception e) {
            System.err.println("Failed to load resource bundle for " + locale + ", falling back to French.");
            bundle = ResourceBundle.getBundle("i18n.messages", new Locale("fr"));
        }
    }

    public String getString(String key) {
        if (bundle.containsKey(key)) {
            return bundle.getString(key);
        }
        return "[" + key + "]";
    }

    public ResourceBundle getBundle() {
        return bundle;
    }
}
