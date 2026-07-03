using System.Collections;
using CraftWorld.Client.Network;
using CraftWorld.Client.Network.Dto;
using UnityEngine;

namespace CraftWorld.Client.World
{
    /// <summary>
    /// WASD movement with server position sync (respects max_speed / max_step).
    /// </summary>
    [RequireComponent(typeof(CharacterController))]
    public sealed class LocalPlayerMotor : MonoBehaviour
    {
        [SerializeField] private float turnSpeed = 720f;

        private GameApiConfig _config;
        private GameApiClient _api;
        private string _characterUuid;
        private CharacterController _controller;
        private WorldStateDto _serverState;
        private float _syncTimer;
        private bool _syncBusy;
        private System.Action<WorldStateDto, PortalUsedDto> _onStateChanged;
        private System.Action<string> _onInteract;

        public void Bind(
            GameApiConfig config,
            GameApiClient api,
            string characterUuid,
            WorldStateDto initialState,
            System.Action<WorldStateDto, PortalUsedDto> onStateChanged,
            System.Action<string> onInteract)
        {
            _config = config;
            _api = api;
            _characterUuid = characterUuid;
            _serverState = initialState;
            _onStateChanged = onStateChanged;
            _onInteract = onInteract;
            _controller = GetComponent<CharacterController>();
            SnapToServer();
        }

        private void Update()
        {
            var input = new Vector2(Input.GetAxisRaw("Horizontal"), Input.GetAxisRaw("Vertical"));
            if (input.sqrMagnitude > 1f)
            {
                input.Normalize();
            }

            var move = new Vector3(input.x, 0f, input.y);
            if (move.sqrMagnitude > 0.001f)
            {
                var targetRot = Quaternion.LookRotation(move, Vector3.up);
                transform.rotation = Quaternion.RotateTowards(
                    transform.rotation, targetRot, turnSpeed * Time.deltaTime);
            }

            var velocity = transform.forward * (move.magnitude * _config.maxSpeed);
            _controller.Move(velocity * Time.deltaTime);

            if (Input.GetKeyDown(KeyCode.E))
            {
                TryInteractNearby();
            }

            _syncTimer += Time.deltaTime;
            if (_syncTimer >= _config.moveSyncInterval && !_syncBusy)
            {
                _syncTimer = 0f;
                TrySyncPosition();
            }
        }

        private void TryInteractNearby()
        {
            var hits = Physics.OverlapSphere(transform.position, _config.interactRadius);
            InteractableProxy closest = null;
            var bestDist = float.MaxValue;

            foreach (var col in hits)
            {
                var proxy = col.GetComponentInParent<InteractableProxy>();
                if (proxy == null)
                {
                    continue;
                }

                var d = Vector3.Distance(transform.position, proxy.transform.position);
                if (d < bestDist)
                {
                    bestDist = d;
                    closest = proxy;
                }
            }

            if (closest != null)
            {
                _onInteract?.Invoke(closest.TargetId);
            }
        }

        private void TrySyncPosition()
        {
            if (_serverState == null)
            {
                return;
            }

            var dx = transform.position.x - _serverState.x;
            var dy = transform.position.y - _serverState.y;
            var dz = transform.position.z - _serverState.z;
            var dist = Mathf.Sqrt(dx * dx + dy * dy + dz * dz);

            if (dist < 0.05f)
            {
                return;
            }

            if (dist > _config.maxStep)
            {
                var scale = _config.maxStep / dist;
                var target = new Vector3(
                    _serverState.x + dx * scale,
                    _serverState.y + dy * scale,
                    _serverState.z + dz * scale);
                transform.position = target;
            }

            _syncBusy = true;
            StartCoroutine(SyncRoutine());
        }

        private IEnumerator SyncRoutine()
        {
            var rotY = transform.eulerAngles.y;
            MoveResponseDto response = null;
            string error = null;

            yield return _api.Move(
                _characterUuid,
                transform.position.x,
                transform.position.y,
                transform.position.z,
                rotY,
                r => response = r,
                e => error = e);

            _syncBusy = false;

            if (error != null)
            {
                Debug.LogWarning($"[Move sync] {error}");
                SnapToServer();
                yield break;
            }

            if (response == null || !response.success)
            {
                Debug.LogWarning($"[Move sync] {response?.error ?? "unknown"}");
                SnapToServer();
                yield break;
            }

            _serverState = response.state;
            SnapToServer();
            _onStateChanged?.Invoke(_serverState, response.portal_used);
        }

        private void SnapToServer()
        {
            if (_serverState == null)
            {
                return;
            }

            _controller.enabled = false;
            transform.position = new Vector3(_serverState.x, _serverState.y, _serverState.z);
            transform.rotation = Quaternion.Euler(0f, _serverState.rotation_y, 0f);
            _controller.enabled = true;
        }
    }
}
