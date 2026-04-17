package tn.unidar.desktop.services.voice;

import java.net.HttpURLConnection;
import java.net.URL;
import java.io.*;
import java.nio.charset.StandardCharsets;
import org.json.JSONObject;

public class ClaudeClient {
    private static final String CLAUDE_API_URL = "https://api.anthropic.com/v1/messages";
    private String apiKey = "YOUR_CLAUDE_API_KEY"; // User should configure this

    public JSONObject classifyIntent(String text) throws Exception {
        if (text == null || text.isBlank()) return createFallbackResponse("UNKNOWN");
        
        // If API key is not configured, use local fallback immediately
        if (apiKey.contains("YOUR_CLAUDE_API_KEY")) {
            return fallbackClassify(text);
        }
        
        try {
            return callClaude(text);
        } catch (Exception e) {
            System.err.println("[ClaudeClient] API Call failed, using fallback: " + e.getMessage());
            return fallbackClassify(text);
        }
    }

    private JSONObject callClaude(String text) throws Exception {
        URL url = java.net.URI.create(CLAUDE_API_URL).toURL();
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setDoOutput(true);
        conn.setRequestProperty("x-api-key", apiKey);
        conn.setRequestProperty("anthropic-version", "2023-06-01");
        conn.setRequestProperty("Content-Type", "application/json");

        JSONObject body = new JSONObject();
        body.put("model", "claude-3-haiku-20240307");
        body.put("max_tokens", 1024);
        
        String systemPrompt = "You are a desktop assistant for Unidar. Classify the user intent into: " +
                             "NAVIGATE_HOME, NAVIGATE_LISTINGS, NAVIGATE_ROOMMATES, NAVIGATE_MESSAGES, " +
                             "NAVIGATE_DASHBOARD, NAVIGATE_VERIFICATION, NAVIGATE_PREMIUM, NAVIGATE_OWNER, " +
                             "SEARCH, LOGIN, REGISTER, LOGOUT, REFRESH, GO_BACK, CLOSE_DRAWER, SWITCH_LANGUAGE, HELP. " +
                             "For SEARCH, extract 'query'. For SWITCH_LANGUAGE, extract 'lang' (en/fr/ar). " +
                             "Return JSON: {intent: 'INTENT', params: {}, reply: 'Short confirmation'}.";
        
        body.put("system", systemPrompt);
        
        JSONObject userMsg = new JSONObject().put("role", "user").put("content", text);
        body.put("messages", new org.json.JSONArray().put(userMsg));

        try (OutputStream os = conn.getOutputStream()) {
            os.write(body.toString().getBytes(StandardCharsets.UTF_8));
        }

        BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
        StringBuilder res = new StringBuilder();
        String line;
        while ((line = br.readLine()) != null) res.append(line);
        
        JSONObject fullResp = new JSONObject(res.toString());
        String content = fullResp.getJSONArray("content").getJSONObject(0).getString("text");
        return new JSONObject(content); // Extract the JSON from Claude's text response
    }

    private JSONObject fallbackClassify(String text) {
        String input = text.toLowerCase();
        String intent = "UNKNOWN";
        
        if (input.contains("home") || input.contains("accueil") || input.contains("main")) {
            intent = "NAVIGATE_HOME";
        } else if (input.contains("listing") || input.contains("annonce") || input.contains("apartment") || input.contains("house")) {
            intent = "NAVIGATE_LISTINGS";
        } else if (input.contains("roommate") || input.contains("coloc")) {
            intent = "NAVIGATE_ROOMMATES";
        } else if (input.contains("message") || input.contains("chat") || input.contains("inbox")) {
            intent = "NAVIGATE_MESSAGES";
        } else if (input.contains("dash") || input.contains("stat") || input.contains("tableau")) {
            intent = "NAVIGATE_DASHBOARD";
        } else if (input.contains("verif") || input.contains("identi")) {
            intent = "NAVIGATE_VERIFICATION";
        } else if (input.contains("premium") || input.contains("sub") || input.contains("abon")) {
            intent = "NAVIGATE_PREMIUM";
        } else if (input.contains("owner") || input.contains("propri") || input.contains("mes annonce")) {
            intent = "NAVIGATE_OWNER";
        } else if (input.contains("login") || input.contains("signin") || input.contains("sign in") || input.contains("connect") || input.contains("connex")) {
            intent = "LOGIN";
        } else if (input.contains("register") || input.contains("signup") || input.contains("inscri")) {
            intent = "REGISTER";
        } else if (input.contains("logout") || input.contains("signout") || input.contains("deconnect")) {
            intent = "LOGOUT";
        } else if (input.contains("search") || input.contains("find") || input.contains("cherch") || input.contains("لوج") || input.contains("بحث")) {
            intent = "SEARCH";
        } else if (input.contains("back") || input.contains("retour") || input.contains("ارجع")) {
            intent = "GO_BACK";
        } else if (input.contains("reload") || input.contains("refresh") || input.contains("actualise") || input.contains("جدد")) {
            intent = "REFRESH";
        } else if (input.contains("close") || input.contains("ferme") || input.contains("سكر")) {
            intent = "CLOSE_DRAWER";
        } else if (input.contains("language") || input.contains("langue") || input.contains("لغة")) {
            intent = "SWITCH_LANGUAGE";
        } else if (input.contains("help") || input.contains("aide") || input.contains("مساعدة")) {
            intent = "HELP";
        }
        
        return createFallbackResponse(intent);
    }

    private JSONObject createFallbackResponse(String intent) {
        JSONObject res = new JSONObject();
        res.put("intent", intent);
        res.put("params", new JSONObject());
        res.put("reply", "Executing " + intent);
        return res;
    }
}
