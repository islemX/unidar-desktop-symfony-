package tn.unidar.desktop.services.voice;

import tn.unidar.desktop.services.NavigationService;
import org.json.JSONObject;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class VoiceController {
    private final SpeechRecognizer recognizer;
    private final ClaudeClient claude;
    private Process backendProcess;
    
    public interface StatusListener { void onStatusChange(String status); }
    private StatusListener statusListener;
    
    public interface CommandListener { void onCommand(String command); }
    private CommandListener commandListener;
    
    public interface StopListener { void onStop(); }
    private StopListener stopListener;

    public VoiceController() {
        recognizer = new SpeechRecognizer();
        claude = new ClaudeClient();
        
        recognizer.setListener(new SpeechRecognizer.RecognitionListener() {
            @Override
            public void onResult(String text) {
                if (statusListener != null) statusListener.onStatusChange("Processing: " + text);
                try {
                    JSONObject result = claude.classifyIntent(text);
                    String intent = result.getString("intent");
                    String reply = result.optString("reply", "OK");
                    
                    handleIntent(intent, result.optJSONObject("params"));
                    speak(reply);
                } catch (Exception e) { 
                    System.err.println("[VoiceController] Voice processing error: " + e.getMessage());
                    if (statusListener != null) statusListener.onStatusChange("Error processing voice.");
                }
            }

            @Override
            public void onError(String error) {
                if (statusListener != null) statusListener.onStatusChange("Error: " + error);
            }

            @Override
            public void onStop() {
                if (stopListener != null) stopListener.onStop();
            }
        });
        
        startBackendService();
    }

    private void startBackendService() {
        new Thread(() -> {
            try {
                // 1. Check if already running
                try {
                    URL health = new URL("http://127.0.0.1:8765/health");
                    java.net.HttpURLConnection conn = (java.net.HttpURLConnection) health.openConnection();
                    conn.setConnectTimeout(1000);
                    if (conn.getResponseCode() == 200) {
                        System.out.println("[Voice] Backend already running.");
                        return;
                    }
                } catch (Exception ignored) {}

                // 2. Resolve Path
                java.io.File serviceFile = new java.io.File("../WorkshopJDBC/voice_service/service.py");
                if (!serviceFile.exists()) {
                    serviceFile = new java.io.File("WorkshopJDBC/voice_service/service.py");
                }
                
                if (!serviceFile.exists()) {
                    System.err.println("[Voice] Could not find service.py. Please run it manually.");
                    return;
                }

                // 3. Start Process via PowerShell Script (handles dependencies)
                System.out.println("[Voice] Auto-starting backend via start_voice.ps1...");
                ProcessBuilder pb = new ProcessBuilder("powershell", "-ExecutionPolicy", "Bypass", "-File", "start_voice.ps1");
                pb.directory(serviceFile.getParentFile());
                pb.inheritIO(); // Critical: see the pip install and model loading progress
                backendProcess = pb.start();

                Runtime.getRuntime().addShutdownHook(new Thread(() -> {
                    if (backendProcess != null && backendProcess.isAlive()) {
                        backendProcess.destroy();
                    }
                }));
            } catch (Exception e) {
                System.err.println("[Voice] Auto-start failed: " + e.getMessage());
            }
        }).start();
    }

    public void setStatusListener(StatusListener listener) { this.statusListener = listener; }
    public void setCommandListener(CommandListener listener) { this.commandListener = listener; }
    public void setStopListener(StopListener listener) { this.stopListener = listener; }

    public void startListening() { 
        String lang = tn.unidar.desktop.services.NavigationService.getInstance().getLanguage();
        recognizer.start(lang); 
    }
    public void stopListening() { recognizer.stop(); }

    private void handleIntent(String intent, JSONObject params) {
        // params could be used for specific search queries
        javafx.application.Platform.runLater(() -> {
            switch (intent) {
                case "NAVIGATE_HOME": NavigationService.getInstance().navigateTo("Home.fxml"); break;
                case "NAVIGATE_LISTINGS": NavigationService.getInstance().navigateTo("Listings.fxml"); break;
                case "NAVIGATE_ROOMMATES": NavigationService.getInstance().navigateTo("Roommates.fxml"); break;
                case "NAVIGATE_MESSAGES": NavigationService.getInstance().navigateTo("Messages.fxml"); break;
                case "NAVIGATE_DASHBOARD": NavigationService.getInstance().navigateTo("Dashboard.fxml"); break;
                case "NAVIGATE_VERIFICATION": NavigationService.getInstance().navigateTo("Verification.fxml"); break;
                case "NAVIGATE_PREMIUM": NavigationService.getInstance().navigateTo("Subscription.fxml"); break;
                case "NAVIGATE_OWNER": NavigationService.getInstance().navigateTo("OwnerListings.fxml"); break;
                case "LOGIN": NavigationService.getInstance().navigateTo("Login.fxml"); break;
                case "REGISTER": NavigationService.getInstance().navigateTo("Register.fxml"); break;
                case "LOGOUT": if (commandListener != null) commandListener.onCommand("LOGOUT"); break;
                case "RELOAD": 
                case "REFRESH": 
                    NavigationService.getInstance().navigateTo(NavigationService.getInstance().getCurrentPage()); 
                    break;
                case "CLOSE_DRAWER":
                    if (commandListener != null) commandListener.onCommand("CLOSE_DRAWER");
                    break;
                case "GO_BACK":
                case "BACK":
                    // Simple "Back to Home" for now, or add history to NavigationService
                    NavigationService.getInstance().navigateTo("Home.fxml");
                    break;
                case "SWITCH_LANGUAGE":
                    if (params != null && params.has("lang")) {
                        if (commandListener != null) commandListener.onCommand("LANG_" + params.getString("lang").toUpperCase());
                    }
                    break;
                case "HELP":
                    speak("I can help you navigate to Home, Listings, Roommates, Messages, Dashboard, or help you change language.");
                    break;
                case "SEARCH":
                    if (params != null && params.has("query")) {
                        NavigationService.getInstance().navigateTo("Listings.fxml", params.getString("query"));
                    } else {
                        NavigationService.getInstance().navigateTo("Listings.fxml");
                    }
                    break;
            }
        });
    }

    public void speak(String text) { speak(text, null); }
    
    public void speak(String text, Runnable onFinished) {
        String lang = tn.unidar.desktop.services.NavigationService.getInstance().getLanguage();
        new Thread(() -> {
            try {
                URL url = java.net.URI.create("http://127.0.0.1:8765/speak").toURL();
                java.net.HttpURLConnection conn = (java.net.HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setDoOutput(true);
                
                String postData = "text=" + java.net.URLEncoder.encode(text, StandardCharsets.UTF_8) + "&language=" + lang;
                try (java.io.OutputStream os = conn.getOutputStream()) {
                    os.write(postData.getBytes(StandardCharsets.UTF_8));
                }
                
                if (conn.getResponseCode() == 200) {
                    byte[] audioBytes = conn.getInputStream().readAllBytes();
                    playWav(audioBytes, onFinished);
                } else if (onFinished != null) {
                    onFinished.run();
                }
            } catch (Exception e) {
                System.err.println("[VoiceController] TTS Error: " + e.getMessage());
                if (onFinished != null) onFinished.run();
            }
        }).start();
    }

    private void playWav(byte[] wavBytes, Runnable onFinished) {
        try {
            javax.sound.sampled.AudioInputStream ais = javax.sound.sampled.AudioSystem.getAudioInputStream(
                new java.io.ByteArrayInputStream(wavBytes));
            javax.sound.sampled.Clip clip = javax.sound.sampled.AudioSystem.getClip();
            clip.open(ais);
            if (onFinished != null) {
                clip.addLineListener(event -> {
                    if (event.getType() == javax.sound.sampled.LineEvent.Type.STOP) {
                        clip.close();
                        onFinished.run();
                    }
                });
            }
            clip.start();
        } catch (Exception e) {
            System.err.println("[VoiceController] Audio play error: " + e.getMessage());
            if (onFinished != null) onFinished.run();
        }
    }
}
