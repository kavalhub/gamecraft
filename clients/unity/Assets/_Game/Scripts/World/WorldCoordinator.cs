using System.Collections;
using CraftWorld.Client.Network;
using CraftWorld.Client.Network.Dto;
using UnityEngine;

namespace CraftWorld.Client.World
{
    /// <summary>
    /// Orchestrates zone visuals, local motor, remote avatars, and server sync.
    /// </summary>
    public sealed class WorldCoordinator : MonoBehaviour
    {
        [SerializeField] private LocalPlayerMotor localPlayerPrefab;
        [SerializeField] private RemotePlayerAvatar remotePlayerPrefab;
        [SerializeField] private InteractableProxy interactablePrefab;
        [SerializeField] private Transform zoneRoot;
        [SerializeField] private Transform playersRoot;

        private GameApiConfig _config;
        private GameApiClient _api;
        private string _characterUuid;
        private LocalPlayerMotor _local;
        private RemotePlayerRegistry _remotes;
        private ZoneVisualBuilder _zoneBuilder;
        private string _currentZone;

        public void Initialize(GameApiConfig config, GameApiClient api)
        {
            _config = config;
            _api = api;

            if (localPlayerPrefab == null)
            {
                localPlayerPrefab = RuntimePrefabFactory.CreateLocalPlayerPrefab();
            }

            if (remotePlayerPrefab == null)
            {
                remotePlayerPrefab = RuntimePrefabFactory.CreateRemotePlayerPrefab();
            }

            if (interactablePrefab == null)
            {
                interactablePrefab = RuntimePrefabFactory.CreateInteractablePrefab();
            }

            if (zoneRoot == null)
            {
                zoneRoot = new GameObject("ZoneRoot").transform;
                zoneRoot.SetParent(transform, false);
            }

            if (playersRoot == null)
            {
                playersRoot = new GameObject("PlayersRoot").transform;
                playersRoot.SetParent(transform, false);
            }

            _remotes = new RemotePlayerRegistry(playersRoot, remotePlayerPrefab);
            _zoneBuilder = new ZoneVisualBuilder(zoneRoot, interactablePrefab, _api);
        }

        public void EnterWorld(string characterUuid, WorldContextDto context)
        {
            _characterUuid = characterUuid;
            _currentZone = context.state.zone_slug;

            StartCoroutine(_zoneBuilder.LoadZone(_characterUuid, _currentZone, OnInteractableClicked));
            SpawnLocalPlayer(context.state);
            ApplyContext(context);
        }

        public void HandleGameEvent(GameEventDto evt)
        {
            if (evt?.payload == null || string.IsNullOrEmpty(evt.payload.character_uuid))
            {
                return;
            }

            if (evt.payload.character_uuid == _characterUuid)
            {
                if (evt.type == "world.entered_zone" && evt.payload.zone_slug != _currentZone)
                {
                    _currentZone = evt.payload.zone_slug;
                    StartCoroutine(_zoneBuilder.LoadZone(_characterUuid, _currentZone, OnInteractableClicked));
                }

                return;
            }

            if (evt.type is "world.moved" or "world.entered_zone")
            {
                if (evt.payload.zone_slug != _currentZone)
                {
                    _remotes.Remove(evt.payload.character_uuid);
                    return;
                }

                _remotes.Upsert(evt.payload.character_uuid, evt.payload.character_name,
                    new Vector3(evt.payload.x, evt.payload.y, evt.payload.z),
                    evt.payload.rotation_y);
            }
        }

        private void SpawnLocalPlayer(WorldStateDto state)
        {
            if (_local != null)
            {
                Destroy(_local.gameObject);
            }

            var pos = ServerToUnity(state);
            _local = Instantiate(localPlayerPrefab, pos.position, pos.rotation, playersRoot);
            _local.gameObject.SetActive(true);
            _local.Bind(_config, _api, _characterUuid, state, OnLocalStateChanged, OnInteractRequested);

            var cam = Camera.main;
            if (cam != null)
            {
                var follow = cam.GetComponent<CameraFollow>() ?? cam.gameObject.AddComponent<CameraFollow>();
                follow.SetTarget(_local.transform);
            }
        }

        private void ApplyContext(WorldContextDto context)
        {
            _remotes.Clear();
            if (context.nearby_players == null)
            {
                return;
            }

            foreach (var player in context.nearby_players)
            {
                if (player.character_uuid == _characterUuid)
                {
                    continue;
                }

                _remotes.Upsert(player.character_uuid, player.name,
                    new Vector3(player.x, player.y, player.z), player.rotation_y);
            }
        }

        private void OnLocalStateChanged(WorldStateDto state, PortalUsedDto portal)
        {
            if (portal != null && portal.to_zone != _currentZone)
            {
                _currentZone = portal.to_zone;
                StartCoroutine(_zoneBuilder.LoadZone(_characterUuid, _currentZone, OnInteractableClicked));
            }
        }

        private void OnInteractRequested(string targetId)
        {
            StartCoroutine(_api.Interact(_characterUuid, targetId,
                resp =>
                {
                    if (resp.success)
                    {
                        Debug.Log($"[Interact] {resp.target_name} → window={resp.window}");
                    }
                    else
                    {
                        Debug.LogWarning($"[Interact] {resp.error}");
                    }
                },
                err => Debug.LogWarning($"[Interact] {err}")));
        }

        private void OnInteractableClicked(string targetId)
        {
            OnInteractRequested(targetId);
        }

        private static (Vector3 position, Quaternion rotation) ServerToUnity(WorldStateDto state)
        {
            var pos = new Vector3(state.x, state.y, state.z);
            var rot = Quaternion.Euler(0f, state.rotation_y, 0f);
            return (pos, rot);
        }
    }
}
