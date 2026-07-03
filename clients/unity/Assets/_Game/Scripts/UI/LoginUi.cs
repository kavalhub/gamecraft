using System;
using CraftWorld.Client.Network;
using CraftWorld.Client.Network.Dto;
using UnityEngine;

namespace CraftWorld.Client.UI
{
    /// <summary>
    /// Minimal IMGUI login screen — no Canvas setup required for Sprint 3 bootstrap.
    /// </summary>
    public sealed class LoginUi : MonoBehaviour
    {
        private GameApiClient _api;
        private Action<LoginResponseDto> _onLoggedIn;
        private bool _visible = true;
        private string _username = "unity_bot";
        private string _password = "test1234";
        private string _status = "";
        private string _error = "";

        public void Bind(GameApiClient api, Action<LoginResponseDto> onLoggedIn)
        {
            _api = api;
            _onLoggedIn = onLoggedIn;
        }

        public void Show() => _visible = true;
        public void Hide() => _visible = false;
        public void SetStatus(string msg) { _status = msg; _error = ""; }
        public void SetError(string msg) { _error = msg; _status = ""; }

        private bool _busy;

        private void OnGUI()
        {
            if (!_visible)
            {
                return;
            }

            const int w = 360;
            const int h = 220;
            var rect = new Rect((Screen.width - w) * 0.5f, (Screen.height - h) * 0.5f, w, h);
            GUI.Box(rect, "Крафт-Мир — Unity Client");

            GUILayout.BeginArea(new Rect(rect.x + 16, rect.y + 36, w - 32, h - 52));
            GUILayout.Label("Username");
            _username = GUILayout.TextField(_username);
            GUILayout.Label("Password");
            _password = GUILayout.PasswordField(_password, '*');

            GUI.enabled = !_busy;
            if (GUILayout.Button("Login"))
            {
                _busy = true;
                _error = "";
                _status = "Logging in…";
                StartCoroutine(_api.Login(_username, _password,
                    resp =>
                    {
                        _busy = false;
                        _onLoggedIn?.Invoke(resp);
                    },
                    err =>
                    {
                        _busy = false;
                        _error = err;
                        _status = "";
                    }));
            }
            GUI.enabled = true;

            if (!string.IsNullOrEmpty(_status))
            {
                GUILayout.Label(_status);
            }

            if (!string.IsNullOrEmpty(_error))
            {
                var prev = GUI.color;
                GUI.color = Color.red;
                GUILayout.Label(_error);
                GUI.color = prev;
            }

            GUILayout.EndArea();
        }
    }
}
