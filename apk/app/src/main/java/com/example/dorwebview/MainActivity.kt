package com.example.dorwebview

import android.annotation.SuppressLint
import android.os.Build
import android.os.Bundle
import android.view.View
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.appcompat.app.AppCompatActivity
import android.view.WindowInsets
import android.view.WindowInsetsController
import android.widget.Button
import android.widget.FrameLayout
import android.view.Gravity
import android.webkit.WebChromeClient
import android.webkit.PermissionRequest
import android.webkit.JsResult
import android.net.http.SslError
import android.webkit.SslErrorHandler
import android.content.pm.PackageManager
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import android.widget.Toast
import java.net.NetworkInterface
import java.util.*

class MainActivity : AppCompatActivity() {

    private fun getCurrentIpAddress(): String {
        try {
            val networkInterfaces = Collections.list(NetworkInterface.getNetworkInterfaces())
            for (networkInterface in networkInterfaces) {
                val inetAddresses = Collections.list(networkInterface.inetAddresses)
                for (inetAddress in inetAddresses) {
                    if (!inetAddress.isLoopbackAddress && inetAddress.hostAddress.indexOf(':') < 0) {
                        val ip = inetAddress.hostAddress
                        // Check if it's a local network IP (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
                        if (ip.startsWith("192.168.") || ip.startsWith("10.") ||
                            ip.matches(Regex("^172\\.(1[6-9]|2[0-9]|3[0-1])\\..*"))) {

                            // Check if default gateway is 192.168.20.254, use hardcoded IP
                            if (isDefaultGateway192_168_20_254()) {
                                return "192.168.21.144"
                            }

                            return ip
                        }
                    }
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
        // Fallback to default IP if detection fails
        return "192.168.21.144"
    }

    private fun isDefaultGateway192_168_20_254(): Boolean {
        try {
            val networkInterfaces = Collections.list(NetworkInterface.getNetworkInterfaces())
            for (networkInterface in networkInterfaces) {
                val inetAddresses = Collections.list(networkInterface.inetAddresses)
                for (inetAddress in inetAddresses) {
                    if (!inetAddress.isLoopbackAddress && inetAddress.hostAddress.indexOf(':') < 0) {
                        val ip = inetAddress.hostAddress
                        if (ip.startsWith("192.168.") || ip.startsWith("10.") ||
                            ip.matches(Regex("^172\\.(1[6-9]|2[0-9]|3[0-1])\\..*"))) {

                            // Try to get default gateway for this interface
                            try {
                                val route = Runtime.getRuntime().exec("ip route show")
                                val reader = route.inputStream.bufferedReader()
                                var line: String?
                                while (reader.readLine().also { line = it } != null) {
                                    if (line?.contains("default") == true && line?.contains("192.168.20.254") == true) {
                                        return true
                                    }
                                }
                            } catch (e: Exception) {
                                // Alternative method for Android
                                try {
                                    val process = Runtime.getRuntime().exec("getprop net.dns1")
                                    val reader = process.inputStream.bufferedReader()
                                    val dns = reader.readLine()
                                    if (dns == "192.168.20.254") {
                                        return true
                                    }
                                } catch (e2: Exception) {
                                    e2.printStackTrace()
                                }
                            }
                        }
                    }
                }
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
        return false
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED
        ) {

            ActivityCompat.requestPermissions(
                this,
                arrayOf(android.Manifest.permission.CAMERA),
                100
            )
        }

        // Fullscreen setup
        hideSystemUI()

        val webView = WebView(this)

        val refreshButton = Button(this)
        refreshButton.text = "⟳"
        refreshButton.setOnClickListener {
            webView.reload()
        }

        val layout = FrameLayout(this)
        layout.addView(webView)
        layout.addView(
            refreshButton, FrameLayout.LayoutParams(
                120, 120, Gravity.BOTTOM or Gravity.END
            ).apply {
                bottomMargin = 40
                rightMargin = 40
            })

        setContentView(layout)

        val settings: WebSettings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.allowFileAccess = true
        settings.allowContentAccess = true
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.mediaPlaybackRequiresUserGesture = false

        webView.webChromeClient = object : android.webkit.WebChromeClient() {
            override fun onJsAlert(
                view: WebView?,
                url: String?,
                message: String?,
                result: android.webkit.JsResult?
            ): Boolean {
                return super.onJsAlert(view, url, message, result)
            }

            override fun onPermissionRequest(request: android.webkit.PermissionRequest) {
                // Automatically grant camera permission for internal use
                request.grant(request.resources)
            }
        }

        webView.webViewClient = object : WebViewClient() {
            override fun onReceivedSslError(
                view: WebView?,
                handler: SslErrorHandler?,
                error: SslError?
            ) {
                handler?.proceed()
            }

            override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                view?.loadUrl(url ?: "")
                return true
            }
        }

        webView.addJavascriptInterface(WebAppInterface(), "AndroidApp")

        webView.loadUrl("https://192.168.21.144:444/paperless/index.php")
        //webView.loadUrl("https://192.168.21.144:444/paperless/index.php")
        //webView.loadUrl("https://192.168.22.145:444/paperless/index.php")
        //webView.loadUrl("https://192.168.21.145/paperless/leader/module/dor-leader-login.php")
    }

    @Suppress("DEPRECATION")
    private fun hideSystemUI() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            try {
                window.setDecorFitsSystemWindows(false)
                window.insetsController?.let {
                    it.hide(WindowInsets.Type.statusBars() or WindowInsets.Type.navigationBars())
                    it.systemBarsBehavior = WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
                }
            } catch (e: Exception) {
                // Fallback for custom Android versions where insetsController may be null
                fallbackLegacyFullscreen()
            }
        } else {
            fallbackLegacyFullscreen()
        }
    }

    @Suppress("DEPRECATION")
    private fun fallbackLegacyFullscreen() {
        window.decorView.systemUiVisibility = (
                View.SYSTEM_UI_FLAG_FULLSCREEN or
                        View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
                        View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                )
    }

    @Deprecated("Back button intentionally disabled")
    @Suppress("MissingSuperCall")
    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == 100 && grantResults.isNotEmpty() &&
            grantResults[0] != PackageManager.PERMISSION_GRANTED
        ) {
            Toast.makeText(this, "Camera permission is required for QR scanning", Toast.LENGTH_LONG)
                .show()
        }
    }

    inner class WebAppInterface {
        @android.webkit.JavascriptInterface
        fun exitApp() {
            runOnUiThread {
                finishAffinity() // Closes all activities
            }
        }
    }
}

