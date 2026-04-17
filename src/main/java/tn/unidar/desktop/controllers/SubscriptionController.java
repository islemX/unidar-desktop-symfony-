package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import org.json.JSONObject;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;

import java.net.URL;
import java.util.ResourceBundle;

public class SubscriptionController implements Initializable {

    @FXML private VBox   alertActive, alertError;
    @FXML private Label  lblError;
    @FXML private Button btnSubscribe;

    // Payment form fields
    @FXML private TextField     txtCardNumber, txtExpiry, txtName;
    @FXML private PasswordField txtCvv;
    @FXML private VBox          paymentForm;

    // Virtual Card Preview
    @FXML private Label lblCardNumber, lblCardHolder, lblCardExpiry;

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        if (!AuthService.getInstance().isLoggedIn()) {
            NavigationService.getInstance().navigateTo("Login.fxml");
            return;
        }
        
        // Fix I18n translation from FXML (Screenshot fix)
        btnSubscribe.setText(tn.unidar.desktop.utils.I18n.t("subscription.activate"));
        
        checkSubscriptionStatus();

        // Enforce 16-digit limit and dynamic Card Preview
        txtCardNumber.textProperty().addListener((obs, oldV, newV) -> {
            if (newV == null) return;
            String digits = newV.replaceAll("\\D", "");
            if (digits.length() > 16) {
                txtCardNumber.setText(oldV);
                return;
            }
            String formatted = formatCardNumber(newV);
            lblCardNumber.setText(formatted.isEmpty() ? "•••• •••• •••• ••••" : formatted);
        });
        
        txtName.textProperty().addListener((obs, oldV, newV) -> {
            lblCardHolder.setText(newV.isEmpty() ? "YOUR NAME" : newV.toUpperCase());
        });
        
        txtExpiry.textProperty().addListener((obs, oldV, newV) -> {
            lblCardExpiry.setText(newV.isEmpty() ? "MM/YY" : newV);
        });

        // Set default name from auth if available
        if (AuthService.getInstance().isLoggedIn()) {
            txtName.setText(AuthService.getInstance().getCurrentUser().getFullName());
        }
    }

    private String formatCardNumber(String s) {
        String digits = s.replaceAll("\\D", "");
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < digits.length() && i < 16; i++) {
            if (i > 0 && i % 4 == 0) sb.append(" ");
            sb.append(digits.charAt(i));
        }
        return sb.toString();
    }

    private void checkSubscriptionStatus() {
        new Thread(() -> {
            try {
                JSONObject resp = ApiClient.getInstance().get("/subscriptions.php");
                String status = resp.optString("subscription_status", "none");
                boolean active = "active".equals(status);
                Platform.runLater(() -> {
                    if (active) {
                        alertActive.setVisible(true);
                        alertActive.setManaged(true);
                        btnSubscribe.setDisable(true);
                        btnSubscribe.setText("✅  Already Premium");
                        if (paymentForm != null) {
                            paymentForm.setVisible(false);
                            paymentForm.setManaged(false);
                        }
                    }
                });
            } catch (Exception e) { e.printStackTrace(); }
        }).start();
    }

    @FXML
    private void subscribe() {
        clearError();

        String cardNumber = txtCardNumber.getText().replaceAll("\\s", "");
        String expiry    = txtExpiry.getText().trim();
        String cvv       = txtCvv.getText().trim();

        if (cardNumber.length() < 13 || cardNumber.length() > 19) {
            showError("Please enter a valid card number.");
            return;
        }
        if (!expiry.matches("\\d{2}/\\d{2}")) {
            showError("Please enter expiry as MM/YY.");
            return;
        }
        if (cvv.length() < 3) {
            showError("Please enter a valid CVV.");
            return;
        }

        btnSubscribe.setDisable(true);
        btnSubscribe.setText("Processing…");

        new Thread(() -> {
            try {
                JSONObject body = new JSONObject()
                        .put("plan", "yearly")
                        .put("payment_method", "card")
                        .put("card_number", cardNumber);
                JSONObject resp = ApiClient.getInstance().post("/subscriptions.php", body);

                Platform.runLater(() -> {
                    if (resp.optBoolean("success", false)) {
                        if (paymentForm != null) {
                            paymentForm.setVisible(false);
                            paymentForm.setManaged(false);
                        }
                        alertActive.setVisible(true);
                        alertActive.setManaged(true);
                        btnSubscribe.setText("✅  Subscription Activated!");

                        Alert alert = new Alert(Alert.AlertType.INFORMATION);
                        alert.setTitle("Welcome to Premium!");
                        alert.setHeaderText("🎉 Subscription Activated!");
                        alert.setContentText("You now have access to all UNIDAR Premium features.\n\n" +
                                "Plan: Annual  |  Amount: 25 TND\n" +
                                "Card ending in: " + cardNumber.substring(cardNumber.length() - 4));
                        alert.showAndWait();
                        NavigationService.getInstance().navigateTo("Dashboard.fxml");
                    } else {
                        showError(resp.optString("error", "Subscription failed."));
                        btnSubscribe.setDisable(false);
                        btnSubscribe.setText("⭐  Activate Premium");
                    }
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    showError("Connection error.");
                    btnSubscribe.setDisable(false);
                    btnSubscribe.setText("⭐  Activate Premium");
                });
            }
        }).start();
    }

    private void showError(String msg) {
        lblError.setText("⚠️  " + msg);
        alertError.setVisible(true);
        alertError.setManaged(true);
    }

    private void clearError() {
        alertError.setVisible(false);
        alertError.setManaged(false);
    }
}
