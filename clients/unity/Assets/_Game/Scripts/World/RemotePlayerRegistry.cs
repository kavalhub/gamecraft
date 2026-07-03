using System.Collections.Generic;
using UnityEngine;

namespace CraftWorld.Client.World
{
    public sealed class RemotePlayerRegistry
    {
        private readonly Transform _root;
        private readonly RemotePlayerAvatar _prefab;
        private readonly Dictionary<string, RemotePlayerAvatar> _byUuid = new();

        public RemotePlayerRegistry(Transform root, RemotePlayerAvatar prefab)
        {
            _root = root;
            _prefab = prefab;
        }

        public void Upsert(string uuid, string name, Vector3 position, float rotationY)
        {
            if (!_byUuid.TryGetValue(uuid, out var avatar))
            {
                avatar = Object.Instantiate(_prefab, _root);
                avatar.SetLabel(name);
                _byUuid[uuid] = avatar;
            }

            avatar.SetTarget(position, rotationY);
        }

        public void Remove(string uuid)
        {
            if (_byUuid.TryGetValue(uuid, out var avatar))
            {
                Object.Destroy(avatar.gameObject);
                _byUuid.Remove(uuid);
            }
        }

        public void Clear()
        {
            foreach (var avatar in _byUuid.Values)
            {
                if (avatar != null)
                {
                    Object.Destroy(avatar.gameObject);
                }
            }

            _byUuid.Clear();
        }
    }
}
