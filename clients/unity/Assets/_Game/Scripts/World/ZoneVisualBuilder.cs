using System;
using System.Collections;
using System.Collections.Generic;
using CraftWorld.Client.Network;
using CraftWorld.Client.Network.Dto;
using UnityEngine;

namespace CraftWorld.Client.World
{
    public sealed class InteractableProxy : MonoBehaviour
    {
        [SerializeField] private TextMesh label;
        [SerializeField] private Color npcColor = new(0.2f, 0.6f, 1f);
        [SerializeField] private Color stationColor = new(0.9f, 0.7f, 0.2f);
        [SerializeField] private Color encounterColor = new(0.9f, 0.3f, 0.3f);

        private Renderer _renderer;
        public string TargetId { get; private set; }

        public void Setup(InteractableDto data)
        {
            TargetId = data.id;
            transform.position = new Vector3(data.x, 0.5f, data.z);

            if (label != null)
            {
                label.text = data.name;
            }

            _renderer = GetComponentInChildren<Renderer>();
            if (_renderer != null)
            {
                var color = data.kind switch
                {
                    "npc" => npcColor,
                    "encounter" => encounterColor,
                    _ => stationColor,
                };
                _renderer.material.color = color;
            }
        }

        private void OnMouseDown()
        {
            // Editor / standalone click fallback
        }
    }

    public sealed class ZoneVisualBuilder
    {
        private readonly Transform _root;
        private readonly InteractableProxy _interactablePrefab;
        private readonly GameApiClient _api;
        private readonly List<GameObject> _spawned = new();

        public ZoneVisualBuilder(Transform root, InteractableProxy prefab, GameApiClient api)
        {
            _root = root;
            _interactablePrefab = prefab;
            _api = api;
        }

        public IEnumerator LoadZone(string characterUuid, string zoneSlug, Action<string> onInteract)
        {
            Clear();

            ZonesResponseDto zones = null;
            yield return _api.GetZones(z => zones = z, err => Debug.LogWarning($"[Zone] {err}"));
            if (zones?.zones == null)
            {
                yield break;
            }

            ZoneMetaDto zone = null;
            foreach (var z in zones.zones)
            {
                if (z.slug == zoneSlug)
                {
                    zone = z;
                    break;
                }
            }

            if (zone == null)
            {
                Debug.LogWarning($"[Zone] Unknown {zoneSlug}");
                yield break;
            }

            BuildGround(zone);
            foreach (var item in zone.interactables ?? new List<InteractableDto>())
            {
                var proxy = UnityEngine.Object.Instantiate(_interactablePrefab, _root);
                proxy.Setup(item);
                _spawned.Add(proxy.gameObject);
            }

            foreach (var portal in zone.portals ?? new List<PortalDto>())
            {
                var gate = GameObject.CreatePrimitive(PrimitiveType.Cylinder);
                gate.name = $"Portal_{portal.id}";
                gate.transform.SetParent(_root, false);
                gate.transform.position = new Vector3(portal.x, 1f, portal.z);
                gate.transform.localScale = new Vector3(2f, 2f, 2f);
                gate.GetComponent<Renderer>().material.color = new Color(0.4f, 0.1f, 0.8f);
                _spawned.Add(gate);
            }

            Debug.Log($"[Zone] Loaded {zone.name} ({zone.slug})");
        }

        private void BuildGround(ZoneMetaDto zone)
        {
            var ground = GameObject.CreatePrimitive(PrimitiveType.Plane);
            ground.name = "Ground";
            ground.transform.SetParent(_root, false);

            var bounds = zone.bounds;
            if (bounds != null)
            {
                var width = bounds.max_x - bounds.min_x;
                var depth = bounds.max_z - bounds.min_z;
                var cx = (bounds.min_x + bounds.max_x) * 0.5f;
                var cz = (bounds.min_z + bounds.max_z) * 0.5f;
                ground.transform.position = new Vector3(cx, 0f, cz);
                ground.transform.localScale = new Vector3(width / 10f, 1f, depth / 10f);
            }
            else
            {
                ground.transform.localScale = new Vector3(10f, 1f, 10f);
            }

            ground.GetComponent<Renderer>().material.color = new Color(0.25f, 0.45f, 0.25f);
            _spawned.Add(ground);
        }

        private void Clear()
        {
            foreach (var go in _spawned)
            {
                if (go != null)
                {
                    UnityEngine.Object.Destroy(go);
                }
            }

            _spawned.Clear();
        }
    }
}
