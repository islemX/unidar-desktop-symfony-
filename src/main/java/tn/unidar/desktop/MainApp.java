package tn.unidar.desktop;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Scene;
import javafx.stage.Stage;

import java.net.CookieHandler;
import java.net.CookieManager;
import java.net.CookiePolicy;

public class MainApp extends Application {
    @Override
    public void start(Stage primaryStage) throws Exception {
        // Enable automatic cookie handling so httpOnly JWT cookie from API is stored/sent
        CookieHandler.setDefault(new CookieManager(null, CookiePolicy.ACCEPT_ALL));

        FXMLLoader loader = new FXMLLoader(getClass().getResource("/fxml/Shell.fxml"));
        Scene scene = new Scene(loader.load(), 1280, 800);
        
        primaryStage.setTitle("UNIDAR Desktop - Student Housing");
        primaryStage.setScene(scene);
        primaryStage.setMinWidth(1000);
        primaryStage.setMinHeight(700);
        primaryStage.show();
    }

    public static void main(String[] args) {
        launch(args);
    }
}
