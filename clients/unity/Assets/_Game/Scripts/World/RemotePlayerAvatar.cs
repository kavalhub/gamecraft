using UnityEngine;

namespace CraftWorld.Client.World
{
    public sealed class RemotePlayerAvatar : MonoBehaviour
    {
        [SerializeField] private float lerpSpeed = 12f;
        [SerializeField] private TextMesh label;

        private Vector3 _targetPos;
        private float _targetRotY;
        private bool _hasTarget;

        public void SetLabel(string name)
        {
            if (label == null)
            {
                label = GetComponentInChildren<TextMesh>();
            }

            if (label != null)
            {
                label.text = name;
            }
        }

        public void SetTarget(Vector3 position, float rotationY)
        {
            _targetPos = position;
            _targetRotY = rotationY;
            _hasTarget = true;
        }

        private void Update()
        {
            if (!_hasTarget)
            {
                return;
            }

            transform.position = Vector3.Lerp(transform.position, _targetPos, Time.deltaTime * lerpSpeed);
            var targetRot = Quaternion.Euler(0f, _targetRotY, 0f);
            transform.rotation = Quaternion.Slerp(transform.rotation, targetRot, Time.deltaTime * lerpSpeed);
        }
    }
}
