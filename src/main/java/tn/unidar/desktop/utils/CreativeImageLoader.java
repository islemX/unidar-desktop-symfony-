package tn.unidar.desktop.utils;

import javafx.animation.Animation;
import javafx.animation.FadeTransition;
import javafx.animation.KeyFrame;
import javafx.animation.KeyValue;
import javafx.animation.Timeline;
import javafx.application.Platform;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.util.Duration;
import tn.unidar.desktop.services.ApiClient;
import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import javafx.embed.swing.SwingFXUtils;

import java.io.ByteArrayInputStream;

/**
 * A creative image loader that provides a "shimmer" skeleton effect,
 * robust byte-based fetching with cookies, and smooth fade-in transitions.
 */
public class CreativeImageLoader {

    /**
     * Loads an image into the parent container with a shimmer effect.
     * @param container The StackPane that will hold the shimmer and the image.
     * @param url The image URL or data URI.
     * @param width Fit width.
     * @param height Fit height.
     * @param fallbackTitle Title for SVG fallback if loading fails.
     * @param fallbackType Property type for SVG fallback.
     */
    public static void load(StackPane container, String url, double width, double height, String fallbackTitle, String fallbackType) {
        // Safety check for dimensions (avoid zero-pixel clipping or shimmer)
        double fitW = (width <= 0) ? 600 : width;
        double fitH = (height <= 0) ? 400 : height;

        container.getChildren().clear();
        container.setMinWidth(fitW);
        container.setMinHeight(fitH);
        container.setMaxWidth(fitW);
        container.setMaxHeight(fitH);

        // 1. Create Shimmer Layer
        Region shimmer = new Region();
        shimmer.setStyle("-fx-background-color: #e2e8f0; -fx-background-radius: 12;");
        
        Timeline timeline = new Timeline(
            new KeyFrame(Duration.ZERO, new KeyValue(shimmer.opacityProperty(), 0.4)),
            new KeyFrame(Duration.millis(800), new KeyValue(shimmer.opacityProperty(), 0.8)),
            new KeyFrame(Duration.millis(1600), new KeyValue(shimmer.opacityProperty(), 0.4))
        );
        timeline.setCycleCount(Animation.INDEFINITE);
        timeline.play();
        
        container.getChildren().add(shimmer);

        // 2. Prepare ImageView
        ImageView imageView = new ImageView();
        imageView.setFitWidth(fitW);
        imageView.setFitHeight(fitH);
        imageView.setPreserveRatio(false);
        imageView.setOpacity(0);
        
        // Clip to rounded corners
        javafx.scene.shape.Rectangle clip = new javafx.scene.shape.Rectangle(fitW, fitH);
        clip.setArcWidth(24); clip.setArcHeight(24);
        imageView.setClip(clip);
        
        container.getChildren().add(imageView);

        // 3. Robust Background Loading
        new Thread(() -> {
            try {
                byte[] data = ApiClient.getInstance().fetchBytes(url);
                if (data != null && data.length > 0) {
                    Image img;
                    try {
                        img = new Image(new ByteArrayInputStream(data));
                        if (img.isError()) throw new Exception("Native decoder failed");
                    } catch (Exception ex) {
                        BufferedImage bi = ImageIO.read(new ByteArrayInputStream(data));
                        if (bi != null) {
                            img = SwingFXUtils.toFXImage(bi, null);
                        } else {
                            throw ex;
                        }
                    }

                    final Image finalImg = img;
                    Platform.runLater(() -> {
                        imageView.setImage(finalImg);
                        timeline.stop();
                        FadeTransition fadeOut = new FadeTransition(Duration.millis(300), shimmer);
                        fadeOut.setToValue(0);
                        fadeOut.setOnFinished(e -> container.getChildren().remove(shimmer));
                        
                        FadeTransition fadeIn = new FadeTransition(Duration.millis(500), imageView);
                        fadeIn.setToValue(1);
                        
                        fadeOut.play();
                        fadeIn.play();
                    });
                } else {
                    throw new Exception("Null or empty data received from ApiClient");
                }
            } catch (Exception e) {
                System.err.println("[CreativeImageLoader] Load failed for: " + url + " - Error: " + e.getMessage());
                
                // Fallback to Proxy
                if (url != null && !url.startsWith("data:")) {
                    try {
                        String proxyUrl = "https://images1-focus-opensocial.googleusercontent.com/gadgets/proxy?container=focus&refresh=2592000&url=" 
                                        + java.net.URLEncoder.encode(url, "UTF-8");
                        byte[] proxyData = ApiClient.getInstance().fetchBytes(proxyUrl);
                        if (proxyData != null && proxyData.length > 0) {
                            Image proxyImg = new Image(new ByteArrayInputStream(proxyData));
                            Platform.runLater(() -> {
                                imageView.setImage(proxyImg);
                                timeline.stop();
                                shimmer.setVisible(false);
                                imageView.setOpacity(1);
                            });
                            return;
                        }
                    } catch (Exception proxyEx) {
                        System.err.println("[CreativeImageLoader] Proxy fallback failed: " + proxyEx.getMessage());
                    }
                }

                // FINAL FALLBACK: Native UI instead of SVG Image
                Platform.runLater(() -> {
                    timeline.stop();
                    shimmer.setVisible(false);
                    
                    VBox fallbackBox = new VBox(10);
                    fallbackBox.setAlignment(javafx.geometry.Pos.CENTER);
                    fallbackBox.setStyle("-fx-background-color: -fx-color-surface-100; -fx-background-radius: 12;");
                    
                    javafx.scene.control.Label iconLabel = new javafx.scene.control.Label(getFallbackEmoji(fallbackType));
                    iconLabel.setStyle("-fx-font-size: 32px;");
                    
                    javafx.scene.control.Label textLabel = new javafx.scene.control.Label(fallbackTitle != null ? fallbackTitle : "Image Unavailable");
                    textLabel.setStyle("-fx-text-fill: -fx-color-surface-500; -fx-font-size: 11px; -fx-font-weight: bold;");
                    textLabel.setWrapText(true);
                    textLabel.setTextAlignment(javafx.scene.text.TextAlignment.CENTER);
                    textLabel.setMaxWidth(fitW - 20);
                    
                    fallbackBox.getChildren().addAll(iconLabel, textLabel);
                    container.getChildren().add(fallbackBox);
                });
            }
        }).start();
    }

    private static String getFallbackEmoji(String type) {
        if (type == null) return "🏠";
        return switch (type.toLowerCase()) {
            case "apartment" -> "🏢";
            case "studio"    -> "🏠";
            case "villa"     -> "🏰";
            case "room"      -> "🛏";
            case "shared"    -> "🤝";
            default          -> "🏠";
        };
    }
}
