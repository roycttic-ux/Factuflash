package com.example.myapplication;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.widget.Button;
import android.widget.EditText;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.example.myapplication.network.ApiClient;
import com.example.myapplication.network.ApiService;
import com.example.myapplication.network.AuthResponse;
import com.example.myapplication.network.RegisterRequest;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class RegisterActivity extends AppCompatActivity {

    private EditText etName, etEmail, etPassword, etPasswordConfirm;
    private Button btnRegisterSubmit, btnBackLogin;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_register);

        etName = findViewById(R.id.etName);
        etEmail = findViewById(R.id.etEmail);
        etPassword = findViewById(R.id.etPassword);
        etPasswordConfirm = findViewById(R.id.etPasswordConfirm);
        btnRegisterSubmit = findViewById(R.id.btnRegisterSubmit);
        btnBackLogin = findViewById(R.id.btnBackLogin);

        btnRegisterSubmit.setOnClickListener(v -> register());

        btnBackLogin.setOnClickListener(v -> {
            finish(); // Cierra Register y vuelve a Login
        });
    }

    private void register() {
        String name = etName.getText().toString();
        String email = etEmail.getText().toString();
        String password = etPassword.getText().toString();
        String passwordConfirm = etPasswordConfirm.getText().toString();

        if (name.isEmpty() || email.isEmpty() || password.isEmpty() || passwordConfirm.isEmpty()) {
            Toast.makeText(this, "Completa todos los campos", Toast.LENGTH_SHORT).show();
            return;
        }

        if (!password.equals(passwordConfirm)) {
            Toast.makeText(this, "Las contraseñas no coinciden", Toast.LENGTH_SHORT).show();
            return;
        }

        btnRegisterSubmit.setEnabled(false);
        btnRegisterSubmit.setText("Registrando...");

        ApiService apiService = ApiClient.getClient().create(ApiService.class);
        Call<AuthResponse> call = apiService.register(new RegisterRequest(name, email, password, passwordConfirm));

        call.enqueue(new Callback<AuthResponse>() {
            @Override
            public void onResponse(Call<AuthResponse> call, Response<AuthResponse> response) {
                btnRegisterSubmit.setEnabled(true);
                btnRegisterSubmit.setText("Registrarse");

                if (response.isSuccessful() && response.body() != null) {
                    AuthResponse authResponse = response.body();
                    
                    SharedPreferences prefs = getSharedPreferences("factuflash_prefs", MODE_PRIVATE);
                    prefs.edit()
                         .putString("token", authResponse.getToken())
                         .putString("user_name", authResponse.getUser().getName())
                         .apply();
                         
                    Toast.makeText(RegisterActivity.this, "¡Cuenta creada con éxito!", Toast.LENGTH_SHORT).show();
                    
                    startActivity(new Intent(RegisterActivity.this, DashboardActivity.class));
                    finishAffinity(); // Limpia la pila de activities
                } else {
                    Toast.makeText(RegisterActivity.this, "Error en el registro. Quizás el email ya existe.", Toast.LENGTH_LONG).show();
                }
            }

            @Override
            public void onFailure(Call<AuthResponse> call, Throwable t) {
                btnRegisterSubmit.setEnabled(true);
                btnRegisterSubmit.setText("Registrarse");
                Toast.makeText(RegisterActivity.this, "Error de red: " + t.getMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }
}
