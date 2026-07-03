using System;
using System.Collections.Generic;

namespace CraftWorld.Client.Network.Dto
{
    [Serializable]
    public sealed class ApiErrorDto
    {
        public bool success;
        public string error;
    }

    [Serializable]
    public sealed class GameMetaDto
    {
        public WorldMetaDto world;
    }

    [Serializable]
    public sealed class WorldMetaDto
    {
        public float max_speed;
        public float max_step;
        public float step_size;
        public float interact_radius;
        public float portal_radius;
        public float nearby_radius;
    }

    [Serializable]
    public sealed class LoginResponseDto
    {
        public string token;
        public string username;
        public List<CharacterSummaryDto> characters;
    }

    [Serializable]
    public sealed class CharacterSummaryDto
    {
        public string uuid;
        public string name;
        public string avatar;
    }

    [Serializable]
    public sealed class WorldStateDto
    {
        public string zone_slug;
        public string zone_name;
        public float x;
        public float y;
        public float z;
        public float rotation_y;
    }

    [Serializable]
    public sealed class WorldContextDto
    {
        public WorldStateDto state;
        public List<NearbyPlayerDto> nearby_players;
        public List<InteractableDto> nearby_interactables;
        public List<PortalDto> nearby_portals;
    }

    [Serializable]
    public sealed class NearbyPlayerDto
    {
        public string character_uuid;
        public string name;
        public float x;
        public float y;
        public float z;
        public float rotation_y;
        public float distance;
    }

    [Serializable]
    public sealed class InteractableDto
    {
        public string id;
        public string kind;
        public string name;
        public float x;
        public float z;
        public string action;
        public string window;
        public float distance;
    }

    [Serializable]
    public sealed class PortalDto
    {
        public string id;
        public float x;
        public float z;
        public string target_zone;
        public float distance;
    }

    [Serializable]
    public sealed class MoveResponseDto
    {
        public bool success;
        public string error;
        public WorldStateDto state;
        public PortalUsedDto portal_used;
    }

    [Serializable]
    public sealed class PortalUsedDto
    {
        public string id;
        public string from_zone;
        public string to_zone;
    }

    [Serializable]
    public sealed class InteractResponseDto
    {
        public bool success;
        public string error;
        public string action;
        public string window;
        public string target_id;
        public string target_name;
    }

    [Serializable]
    public sealed class ZonesResponseDto
    {
        public List<ZoneMetaDto> zones;
    }

    [Serializable]
    public sealed class ZoneMetaDto
    {
        public string slug;
        public string name;
        public string description;
        public ZoneBoundsDto bounds;
        public List<PortalDto> portals;
        public List<InteractableDto> interactables;
    }

    [Serializable]
    public sealed class ZoneBoundsDto
    {
        public float min_x;
        public float max_x;
        public float min_z;
        public float max_z;
    }

    [Serializable]
    public sealed class GameEventDto
    {
        public long id;
        public string type;
        public string occurred_at;
        public GameEventPayloadDto payload;
    }

    [Serializable]
    public sealed class GameEventPayloadDto
    {
        public string character_uuid;
        public string character_name;
        public string zone_slug;
        public string previous_zone;
        public float x;
        public float y;
        public float z;
        public float rotation_y;
        public string target_id;
    }
}
