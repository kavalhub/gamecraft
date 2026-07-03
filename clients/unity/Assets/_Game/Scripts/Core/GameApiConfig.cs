using System;
using UnityEngine;

namespace CraftWorld.Client
{
    /// <summary>
    /// Server URL and session. Assign in Inspector or via Resources/GameApiConfig.
    /// </summary>
    [CreateAssetMenu(fileName = "GameApiConfig", menuName = "CraftWorld/Game API Config")]
    public sealed class GameApiConfig : ScriptableObject
    {
        [Header("Server")]
        [Tooltip("Base URL without trailing slash, e.g. http://local.game.local")]
        public string baseUrl = "http://local.game.local";

        [Header("Movement (overridden by GET /api/game/meta)")]
        public float maxSpeed = 15f;
        public float maxStep = 12f;
        public float interactRadius = 5f;
        public float moveSyncInterval = 0.25f;

        [Header("WebSocket")]
        public bool useWebSocket = true;

        public Uri ApiUri(string path)
        {
            var normalized = path.StartsWith("/") ? path : "/" + path;
            return new Uri(baseUrl.TrimEnd('/') + normalized);
        }

        public Uri WebSocketUri(string characterUuid, long lastEventId)
        {
            var baseUri = new Uri(baseUrl);
            var scheme = baseUri.Scheme == "https" ? "wss" : "ws";
            var host = baseUri.IsDefaultPort
                ? baseUri.Host
                : $"{baseUri.Host}:{baseUri.Port}";
            var query = $"character_uuid={Uri.EscapeDataString(characterUuid)}&last_event_id={lastEventId}";
            return new Uri($"{scheme}://{host}/ws?{query}");
        }
    }
}
