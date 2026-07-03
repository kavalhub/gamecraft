using System;
using System.Collections;
using System.Text;
using CraftWorld.Client.Network.Dto;
using Newtonsoft.Json;
using UnityEngine;
using UnityEngine.Networking;

namespace CraftWorld.Client.Network
{
    public sealed class GameApiClient
    {
        private readonly GameApiConfig _config;
        private string _token;

        public GameApiClient(GameApiConfig config)
        {
            _config = config;
        }

        public string Token => _token;

        public void SetToken(string token) => _token = token;

        public IEnumerator FetchMeta(Action<WorldMetaDto> onOk, Action<string> onError)
        {
            yield return Get("/api/game/meta", null, json =>
            {
                var meta = JsonConvert.DeserializeObject<GameMetaDto>(json);
                if (meta?.world != null)
                {
                    _config.maxSpeed = meta.world.max_speed;
                    _config.maxStep = meta.world.max_step;
                    _config.interactRadius = meta.world.interact_radius;
                }
                onOk?.Invoke(meta?.world);
            }, onError);
        }

        public IEnumerator Login(string username, string password, Action<LoginResponseDto> onOk, Action<string> onError)
        {
            var body = JsonConvert.SerializeObject(new { username, password });
            yield return Post("/api/login", body, null, json =>
            {
                var response = JsonConvert.DeserializeObject<LoginResponseDto>(json);
                if (response == null || string.IsNullOrEmpty(response.token))
                {
                    onError?.Invoke("Invalid login response");
                    return;
                }
                _token = response.token;
                onOk?.Invoke(response);
            }, onError);
        }

        public IEnumerator GetWorldContext(string characterUuid, Action<WorldContextDto> onOk, Action<string> onError)
        {
            yield return Get($"/api/world/{characterUuid}/context", _token, json =>
            {
                var ctx = JsonConvert.DeserializeObject<WorldContextDto>(json);
                onOk?.Invoke(ctx);
            }, onError);
        }

        public IEnumerator GetZones(Action<ZonesResponseDto> onOk, Action<string> onError)
        {
            yield return Get("/api/world/zones", _token, json =>
            {
                onOk?.Invoke(JsonConvert.DeserializeObject<ZonesResponseDto>(json));
            }, onError);
        }

        public IEnumerator Move(string characterUuid, float x, float y, float z, float rotationY,
            Action<MoveResponseDto> onOk, Action<string> onError)
        {
            var body = JsonConvert.SerializeObject(new { x, y, z, rotation_y = rotationY });
            yield return Post($"/api/world/{characterUuid}/move", body, _token, json =>
            {
                onOk?.Invoke(JsonConvert.DeserializeObject<MoveResponseDto>(json));
            }, onError);
        }

        public IEnumerator Interact(string characterUuid, string targetId,
            Action<InteractResponseDto> onOk, Action<string> onError)
        {
            var body = JsonConvert.SerializeObject(new { target_id = targetId });
            yield return Post($"/api/world/{characterUuid}/interact", body, _token, json =>
            {
                onOk?.Invoke(JsonConvert.DeserializeObject<InteractResponseDto>(json));
            }, onError);
        }

        private IEnumerator Get(string path, string token, Action<string> onOk, Action<string> onError)
        {
            using var req = UnityWebRequest.Get(_config.ApiUri(path).ToString());
            ApplyAuth(req, token);
            yield return req.SendWebRequest();

            if (req.result != UnityWebRequest.Result.Success)
            {
                onError?.Invoke(ParseError(req));
                yield break;
            }

            onOk?.Invoke(req.downloadHandler.text);
        }

        private IEnumerator Post(string path, string jsonBody, string token, Action<string> onOk, Action<string> onError)
        {
            using var req = new UnityWebRequest(_config.ApiUri(path).ToString(), "POST");
            var bytes = Encoding.UTF8.GetBytes(jsonBody);
            req.uploadHandler = new UploadHandlerRaw(bytes);
            req.downloadHandler = new DownloadHandlerBuffer();
            req.SetRequestHeader("Content-Type", "application/json");
            req.SetRequestHeader("Accept", "application/json");
            ApplyAuth(req, token);
            yield return req.SendWebRequest();

            if (req.result != UnityWebRequest.Result.Success)
            {
                onError?.Invoke(ParseError(req));
                yield break;
            }

            onOk?.Invoke(req.downloadHandler.text);
        }

        private static void ApplyAuth(UnityWebRequest req, string token)
        {
            if (!string.IsNullOrEmpty(token))
            {
                req.SetRequestHeader("Authorization", "Bearer " + token);
            }
        }

        private static string ParseError(UnityWebRequest req)
        {
            if (!string.IsNullOrEmpty(req.downloadHandler?.text))
            {
                try
                {
                    var err = JsonConvert.DeserializeObject<ApiErrorDto>(req.downloadHandler.text);
                    if (!string.IsNullOrEmpty(err?.error))
                    {
                        return err.error;
                    }
                }
                catch
                {
                    // fall through
                }
            }

            return $"{req.responseCode}: {req.error}";
        }
    }
}
