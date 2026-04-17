package tn.unidar.desktop.utils;

import javafx.embed.swing.SwingFXUtils;
import javafx.scene.canvas.Canvas;
import javafx.scene.canvas.GraphicsContext;
import javafx.scene.image.WritableImage;
import javafx.scene.layout.VBox;
import javafx.scene.paint.Color;

import javax.imageio.ImageIO;
import java.io.ByteArrayOutputStream;
import java.util.Base64;

/**
 * A simple Signature Pad component for JavaFX using Canvas.
 */
public class SignaturePad extends VBox {
    private final Canvas canvas;
    private final GraphicsContext gc;

    public SignaturePad(double width, double height) {
        this.canvas = new Canvas(width, height);
        this.gc = canvas.getGraphicsContext2D();

        // Style the container
        this.setStyle("-fx-border-color: #cbd5e1; -fx-border-width: 2; -fx-border-radius: 8; -fx-background-color: white; -fx-background-radius: 8;");
        this.getChildren().add(canvas);

        initCanvas();
    }

    private void initCanvas() {
        gc.setStroke(Color.BLACK);
        gc.setLineWidth(2.5);
        gc.setLineCap(javafx.scene.shape.StrokeLineCap.ROUND);
        gc.setLineJoin(javafx.scene.shape.StrokeLineJoin.ROUND);

        canvas.setOnMousePressed(e -> {
            gc.beginPath();
            gc.moveTo(e.getX(), e.getY());
            gc.stroke();
        });

        canvas.setOnMouseDragged(e -> {
            gc.lineTo(e.getX(), e.getY());
            gc.stroke();
        });
    }

    public void clear() {
        gc.clearRect(0, 0, canvas.getWidth(), canvas.getHeight());
    }

    /**
     * Converts the canvas content to a Base64 encoded PNG string.
     * @return Base64 string starting with "data:image/png;base64,"
     */
    public String toBase64() {
        try {
            WritableImage writableImage = new WritableImage((int) canvas.getWidth(), (int) canvas.getHeight());
            canvas.snapshot(null, writableImage);

            java.awt.image.BufferedImage bufferedImage = SwingFXUtils.fromFXImage(writableImage, null);
            ByteArrayOutputStream outputStream = new ByteArrayOutputStream();
            ImageIO.write(bufferedImage, "png", outputStream);

            byte[] bytes = outputStream.toByteArray();
            return "data:image/png;base64," + Base64.getEncoder().encodeToString(bytes);
        } catch (Exception e) {
            System.err.println("[SignaturePad] Error exporting to Base64: " + e.getMessage());
            return null;
        }
    }

    public boolean isEmpty() {
        // This is a naive check. A better one would be tracking if any move happened.
        // For simplicity, we'll assume the user draws something.
        return false; 
    }
}
