using CraftWorld.Client.Network;
using CraftWorld.Client.World;
using UnityEngine;

namespace CraftWorld.Client
{
    /// <summary>
    /// Third-person camera follow for local player.
    /// </summary>
    public sealed class CameraFollow : MonoBehaviour
    {
        [SerializeField] private Transform target;
        [SerializeField] private Vector3 offset = new(0f, 12f, -18f);
        [SerializeField] private float smooth = 8f;

        public void SetTarget(Transform t) => target = t;

        private void LateUpdate()
        {
            if (target == null)
            {
                return;
            }

            var desired = target.position + offset;
            transform.position = Vector3.Lerp(transform.position, desired, Time.deltaTime * smooth);
            transform.LookAt(target.position + Vector3.up * 1.5f);
        }
    }
}
