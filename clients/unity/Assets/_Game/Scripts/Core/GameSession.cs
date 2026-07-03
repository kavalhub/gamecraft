using System.Collections;
using CraftWorld.Client.Network;
using CraftWorld.Client.Network.Dto;
using CraftWorld.Client.UI;
using CraftWorld.Client.World;
using UnityEngine;

namespace CraftWorld.Client
{
    /// <summary>
    /// Entry point: login → load world context → spawn local player + zone visuals.
    /// Attach to a single GameObject in the bootstrap scene.
    /// </summary>
    public sealed class GameSession : MonoBehaviour
    {
        [SerializeField] private GameApiConfig config;
        [SerializeField] private LoginUi loginUi;
        [SerializeField] private WorldHud worldHud;
        [SerializeField] private WorldCoordinator worldCoordinator;

        private GameApiClient _api;
        private GameEventSocket _socket;
        private string _characterUuid;

        private void Awake()
        {
            if (config == null)
            {
                config = Resources.Load<GameApiConfig>("GameApiConfig");
            }

            if (config == null)
            {
                config = ScriptableObject.CreateInstance<GameApiConfig>();
                Debug.LogWarning("[GameSession] No GameApiConfig asset — using defaults (http://local.game.local)");
            }

            if (loginUi == null)
            {
                loginUi = gameObject.AddComponent<LoginUi>();
            }

            if (worldHud == null)
            {
                worldHud = gameObject.AddComponent<WorldHud>();
            }

            if (worldCoordinator == null)
            {
                worldCoordinator = gameObject.AddComponent<WorldCoordinator>();
            }

            _api = new GameApiClient(config);
            _socket = new GameEventSocket();
            worldCoordinator.Initialize(config, _api);
        }

        private void Start()
        {
            loginUi.Bind(_api, OnLoggedIn);
            loginUi.Show();
        }

        private void Update()
        {
            _socket?.Pump();
        }

        private void OnDestroy()
        {
            _socket?.Dispose();
        }

        private void OnLoggedIn(LoginResponseDto response)
        {
            if (response.characters == null || response.characters.Count == 0)
            {
                loginUi.SetError("No characters on account");
                return;
            }

            _characterUuid = response.characters[0].uuid;
            StartCoroutine(EnterWorld(response.characters[0].name));
        }

        private IEnumerator EnterWorld(string characterName)
        {
            loginUi.SetStatus("Loading world meta…");
            WorldMetaDto meta = null;
            yield return _api.FetchMeta(m => meta = m, err => loginUi.SetError(err));

            loginUi.SetStatus("Loading zone…");
            WorldContextDto context = null;
            yield return _api.GetWorldContext(_characterUuid, c => context = c, err => loginUi.SetError(err));
            if (context?.state == null)
            {
                loginUi.SetError("Failed to load world context");
                yield break;
            }

            loginUi.Hide();
            worldHud.Show(characterName, context.state);
            worldCoordinator.EnterWorld(_characterUuid, context);

            if (config.useWebSocket)
            {
                _ = _socket.ConnectAsync(config, _characterUuid);
                _socket.OnEvent += worldCoordinator.HandleGameEvent;
            }
        }
    }
}
