package tn.unidar.desktop.services;

import tn.unidar.desktop.models.User;

public class AuthService {
    private static AuthService instance;
    private User currentUser;

    private AuthService() {}

    public static AuthService getInstance() {
        if (instance == null) instance = new AuthService();
        return instance;
    }

    public User getCurrentUser() { return currentUser; }
    public void setCurrentUser(User user) { this.currentUser = user; }
    public boolean isLoggedIn() { return currentUser != null; }

    public org.json.JSONObject getCurrentUserAsJson() {
        if (currentUser == null) return new org.json.JSONObject();
        return new org.json.JSONObject()
            .put("id", currentUser.getId())
            .put("full_name", currentUser.getFullName())
            .put("email", currentUser.getEmail())
            .put("role", currentUser.getRole())
            .put("phone", currentUser.getPhone());
    }

    public void logout() {
        currentUser = null;
        ApiClient.getInstance().clearAuth();
    }
}
