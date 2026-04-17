package tn.unidar.desktop.services;

import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.layout.StackPane;
import tn.unidar.desktop.services.LanguageManager;

public class NavigationService {
    private static NavigationService instance;
    private StackPane contentArea;
    private Object pendingData;
    private String language = "en";
    private String currentPage = "Home.fxml";

    public interface NavigationListener {
        void onNavigate(String page);
    }
    private NavigationListener listener;
    private Runnable authChangeListener;

    private NavigationService() {}

    public static NavigationService getInstance() {
        if (instance == null) instance = new NavigationService();
        return instance;
    }

    public void setContentArea(StackPane area) {
        this.contentArea = area;
    }

    public void setNavigationListener(NavigationListener l) {
        this.listener = l;
    }

    public void setAuthChangeListener(Runnable r) {
        this.authChangeListener = r;
    }

    public String getLanguage() { return language; }
    public void setLanguage(String lang) { this.language = lang; }

    public String getCurrentPage() { return currentPage; }

    public void notifyAuthChange() {
        if (authChangeListener != null) authChangeListener.run();
    }

    /** Navigate without data */
    public void navigateTo(String fxmlPath) {
        navigateTo(fxmlPath, null);
    }

    /** Store transient data to be consumed by the next page/controller. */
    public void setData(Object data) {
        this.pendingData = data;
    }

    /** Navigate with context data (e.g., listing id) */
    public void navigateTo(String fxmlPath, Object data) {
        this.pendingData = data;
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/" + fxmlPath));
            loader.setResources(LanguageManager.getInstance().getBundle());
            Parent page = loader.load();
            contentArea.getChildren().setAll(page);
            currentPage = fxmlPath;
            if (listener != null) listener.onNavigate(fxmlPath);
        } catch (Exception e) {
            e.printStackTrace();
            System.err.println("Navigation failed: /fxml/" + fxmlPath);
        }
    }

    /** Retrieve and consume the pending navigation data */
    @SuppressWarnings("unchecked")
    public <T> T consumeData() {
        Object d = pendingData;
        pendingData = null;
        return (T) d;
    }
}
