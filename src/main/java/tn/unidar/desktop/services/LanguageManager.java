package tn.unidar.desktop.services;

import javafx.beans.property.ObjectProperty;
import javafx.beans.property.SimpleObjectProperty;
import java.util.Locale;
import java.util.ResourceBundle;

public class LanguageManager {
    private static LanguageManager instance;
    private final ObjectProperty<Locale> locale = new SimpleObjectProperty<>(Locale.ENGLISH);

    private LanguageManager() {}

    public static LanguageManager getInstance() {
        if (instance == null) instance = new LanguageManager();
        return instance;
    }

    public ObjectProperty<Locale> localeProperty() {
        return locale;
    }

    public Locale getLocale() {
        return locale.get();
    }

    public void setLocale(Locale newLocale) {
        ResourceBundle.clearCache();
        locale.set(newLocale);
    }

    public ResourceBundle getBundle() {
        return ResourceBundle.getBundle("i18n.messages", getLocale());
    }

    public String getString(String key) {
        try {
            return getBundle().getString(key);
        } catch (Exception e) {
            return "!" + key + "!";
        }
    }
}
