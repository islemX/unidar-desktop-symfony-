package tn.unidar.desktop.controllers;

import javafx.application.Platform;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.VBox;
import org.json.JSONObject;
import tn.unidar.desktop.models.User;
import tn.unidar.desktop.services.ApiClient;
import tn.unidar.desktop.services.AuthService;
import tn.unidar.desktop.services.NavigationService;

public class LoginController {

    @FXML private TextField     txtEmail;
    @FXML private PasswordField txtPassword;
    @FXML private VBox          alertError;
    @FXML private Label         lblError;
    @FXML private Button        btnLogin;

    @FXML
    private void handleLogin() {
        clearError();
        String email = txtEmail.getText().trim();
        String pass  = txtPassword.getText();

        if (email.isEmpty() || pass.isEmpty()) {
            showError("Please enter your email and password.");
            return;
        }

        btnLogin.setDisable(true);
        btnLogin.setText("Signing in…");

        new Thread(() -> {
            try {
                JSONObject body = new JSONObject()
                        .put("email", email)
                        .put("password", pass);
                JSONObject resp = ApiClient.getInstance().postAndCaptureCookie("/auth.php?action=login", body);

                Platform.runLater(() -> {
                    btnLogin.setDisable(false);
                    btnLogin.setText("Sign In");

                    // API returns {success:true, user:{id,email,full_name,role}}
                    // httpOnly JWT cookie is stored automatically by CookieManager
                    if (resp.optBoolean("success", false) && resp.has("user")) {
                        JSONObject uJson = resp.getJSONObject("user");
                        User user = new User(
                                uJson.getInt("id"),
                                uJson.getString("full_name"),
                                uJson.getString("email"),
                                uJson.getString("role")
                        );
                        if (uJson.has("phone")) user.setPhone(uJson.optString("phone"));

                        AuthService.getInstance().setCurrentUser(user);
                        // Notify shell to refresh sidebar (show auth nav)
                        NavigationService.getInstance().notifyAuthChange();
                        // Route admin to admin dashboard, others to user dashboard
                        String role = uJson.optString("role", "student");
                        if ("admin".equalsIgnoreCase(role)) {
                            NavigationService.getInstance().navigateTo("AdminDashboard.fxml");
                        } else {
                            NavigationService.getInstance().navigateTo("Dashboard.fxml");
                        }
                    } else {
                        String errMsg = resp.optString("error", "");
                        if (errMsg.isEmpty()) errMsg = "Invalid email or password. Please try again.";
                        showError(errMsg);
                    }
                });
            } catch (Exception e) {
                e.printStackTrace();
                Platform.runLater(() -> {
                    btnLogin.setDisable(false);
                    btnLogin.setText("Sign In");
                    showError("Connection error. Please check your internet and try again.");
                });
            }
        }).start();
    }

    @FXML private void gotoRegister() { NavigationService.getInstance().navigateTo("Register.fxml"); }

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
