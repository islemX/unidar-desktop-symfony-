package tn.unidar.desktop.services.voice;

import java.io.*;
import java.net.*;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import javax.sound.sampled.*;
import org.json.JSONObject;

public class SpeechRecognizer {
    private static final String TRANSCRIPTION_URL = "http://127.0.0.1:8765/transcribe";
    private TargetDataLine line;
    private boolean isRunning = false;
    private String language = "en";
    private ByteArrayOutputStream audioBuffer;
    private long lastSpeakTime = 0;
    private long startTime = 0;
    private boolean hasSpoken = false;
    private static final long INITIAL_GRACE_PERIOD_MS = 3000;
    private static final long SILENCE_THRESHOLD_MS = 1500;
    private static final int VOLUME_THRESHOLD = 200; // Lowered for better sensitivity

    public interface RecognitionListener { 
        void onResult(String text); 
        void onError(String error);
        void onStop();
    }
    private RecognitionListener listener;

    public void setListener(RecognitionListener listener) { this.listener = listener; }

    public void start(String lang) {
        if (isRunning) return;
        isRunning = true;
        this.language = lang;
        this.startTime = System.currentTimeMillis();
        this.lastSpeakTime = startTime;
        this.hasSpoken = false;
        audioBuffer = new ByteArrayOutputStream();
        new Thread(this::captureAudio).start();
    }

    public void stop() {
        if (!isRunning) return;
        isRunning = false;
        if (line != null) { line.stop(); line.close(); }
        if (listener != null) listener.onStop();
        
        // Send the captured audio to the service
        new Thread(this::sendTranscriptionRequest).start();
    }

    private void captureAudio() {
        try {
            AudioFormat format = new AudioFormat(16000, 16, 1, true, false);
            DataLine.Info info = new DataLine.Info(TargetDataLine.class, format);
            line = (TargetDataLine) AudioSystem.getLine(info);
            line.open(format);
            line.start();

            byte[] buffer = new byte[4096];
            while (isRunning) {
                int count = line.read(buffer, 0, buffer.length);
                if (count > 0) {
                    audioBuffer.write(buffer, 0, count);
                    
                    // Simple peak volume check for silence detection
                    int max = 0;
                    for (int i = 0; i < count - 1; i += 2) {
                        short sample = (short) ((buffer[i + 1] << 8) | (buffer[i] & 0xff));
                        max = Math.max(max, Math.abs(sample));
                    }
                    
                    if (max > VOLUME_THRESHOLD) {
                        long now = System.currentTimeMillis();
                        if (now - startTime > 2500) { // Ignore welcome speech window
                            lastSpeakTime = now;
                            hasSpoken = true;
                        }
                    } else {
                        long now = System.currentTimeMillis();
                        // Only auto-stop if we've heard speech OR we've waited past the initial grace period
                        if (hasSpoken && (now - lastSpeakTime > SILENCE_THRESHOLD_MS)) {
                            stop();
                        } else if (!hasSpoken && (now - startTime > INITIAL_GRACE_PERIOD_MS + 2000)) {
                            // If user hasn't spoken at all after 5s total, stop to avoid hanging
                            stop();
                        }
                    }
                }
            }
        } catch (Exception e) { 
            System.err.println("[SpeechRecognizer] Capture error: " + e.getMessage());
        }
    }

    private void sendTranscriptionRequest() {
        if (audioBuffer == null || audioBuffer.size() == 0) return;
        
        try {
            byte[] audioData = audioBuffer.toByteArray();
            byte[] wavData = addWavHeader(audioData);
            
            String boundary = "---UnidarBoundary" + System.currentTimeMillis();
            URL url = java.net.URI.create(TRANSCRIPTION_URL).toURL();
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);

            try (DataOutputStream dos = new DataOutputStream(conn.getOutputStream())) {
                dos.writeBytes("--" + boundary + "\r\n");
                dos.writeBytes("Content-Disposition: form-data; name=\"audio\"; filename=\"input.wav\"\r\n");
                dos.writeBytes("Content-Type: audio/wav\r\n\r\n");
                dos.write(wavData);
                dos.writeBytes("\r\n");
                
                if (language != null) {
                    dos.writeBytes("--" + boundary + "\r\n");
                    dos.writeBytes("Content-Disposition: form-data; name=\"language\"\r\n\r\n");
                    dos.writeBytes(language + "\r\n");
                }
                
                dos.writeBytes("--" + boundary + "--\r\n");
            }

            if (conn.getResponseCode() == 200) {
                BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = br.readLine()) != null) sb.append(line);
                
                JSONObject json = new JSONObject(sb.toString());
                if (json.has("text") && !json.getString("text").isEmpty() && listener != null) {
                    listener.onResult(json.getString("text"));
                }
            } else {
                if (listener != null) listener.onError("Service error: " + conn.getResponseCode());
            }
        } catch (Exception e) {
            String msg = e.getMessage();
            if (msg != null && msg.contains("refused")) msg = "Service offline (Run start_voice.ps1)";
            if (listener != null) listener.onError(msg);
            System.err.println("[SpeechRecognizer] Error: " + msg);
        }
    }

    private byte[] addWavHeader(byte[] pcmData) {
        int sampleRate = 16000;
        int channels = 1;
        int bitsPerSample = 16;
        int byteRate = sampleRate * channels * bitsPerSample / 8;
        int dataSize = pcmData.length;
        int fileSize = 36 + dataSize;

        ByteBuffer buffer = ByteBuffer.allocate(44 + dataSize);
        buffer.order(ByteOrder.LITTLE_ENDIAN);
        buffer.put("RIFF".getBytes());
        buffer.putInt(fileSize);
        buffer.put("WAVE".getBytes());
        buffer.put("fmt ".getBytes());
        buffer.putInt(16);
        buffer.putShort((short) 1);
        buffer.putShort((short) channels);
        buffer.putInt(sampleRate);
        buffer.putInt(byteRate);
        buffer.putShort((short) (channels * bitsPerSample / 8));
        buffer.putShort((short) bitsPerSample);
        buffer.put("data".getBytes());
        buffer.putInt(dataSize);
        buffer.put(pcmData);

        return buffer.array();
    }
}
