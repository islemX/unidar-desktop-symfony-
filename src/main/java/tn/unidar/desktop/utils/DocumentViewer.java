package tn.unidar.desktop.utils;

import javafx.geometry.Pos;
import javafx.scene.Scene;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;
import javafx.scene.web.WebView;
import javafx.stage.Modality;
import javafx.stage.Stage;
import tn.unidar.desktop.services.ApiClient;

/**
 * A reusable modal utility to display verification documents (Images or PDFs).
 */
public class DocumentViewer {

    public static void show(String titleStr, String imagePath) {
        Stage dialog = new Stage();
        dialog.initModality(Modality.APPLICATION_MODAL);
        dialog.setTitle(titleStr);

        VBox root = new VBox(0);
        root.setStyle("-fx-background-color: #f8fafc;");
        root.setPrefWidth(800);
        root.setPrefHeight(900);

        // ── Header ──────────────────────────────────────────────────────
        HBox header = new HBox(15);
        header.setAlignment(Pos.CENTER_LEFT);
        header.setStyle("-fx-padding: 16 24; -fx-background-color: white; " +
                      "-fx-border-color: transparent transparent #e2e8f0 transparent; -fx-border-width: 0 0 1 0;");

        Label title = new Label(titleStr);
        title.setStyle("-fx-font-size: 16px; -fx-font-weight: bold; -fx-text-fill: #0f172a;");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button btnClose = new Button("✕");
        btnClose.setStyle("-fx-background-color: transparent; -fx-text-fill: #94a3b8; -fx-font-size: 18px; -fx-cursor: hand;");
        btnClose.setOnAction(e -> dialog.close());
        
        header.getChildren().addAll(title, spacer, btnClose);

        // ── WebView ──────────────────────────────────────────────────────
        WebView webView = new WebView();
        VBox.setVgrow(webView, Priority.ALWAYS);
        
        // Resolve URL
        String fullUrl = ApiClient.resolveImageUrl(imagePath);
        
        // We use a simple HTML wrapper to ensure the image fits and is centered, 
        // especially if the URL is a direct image path.
        String html = "<!DOCTYPE html><html><head>" +
                      "<style>" +
                      "body { margin: 0; padding: 20px; display: flex; justify-content: center; background: #f1f5f9; font-family: sans-serif; }" +
                      "img, embed, iframe { max-width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 8px; background: white; }" +
                      "</style></head><body>";
        
        if (imagePath.toLowerCase().endsWith(".pdf")) {
            html += "<embed src=\"" + fullUrl + "\" type=\"application/pdf\" width=\"100%\" height=\"800px\" />";
        } else {
            html += "<img src=\"" + fullUrl + "\" alt=\"Document\" />";
        }
        
        html += "</body></html>";
        
        webView.getEngine().loadContent(html);

        root.getChildren().addAll(header, webView);

        Scene scene = new Scene(root);
        dialog.setScene(scene);
        dialog.show();
    }
}
