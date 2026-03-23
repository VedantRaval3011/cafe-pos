import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

/* ═══════════════════════════════════════════════════════════════════
   TEXTURE GENERATORS — procedural canvas textures for the voxel look
   ═══════════════════════════════════════════════════════════════════ */

function canvasTex(w, h, fn) {
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    fn(c.getContext('2d'), w, h);
    const t = new THREE.CanvasTexture(c);
    t.wrapS = t.wrapT = THREE.RepeatWrapping;
    t.colorSpace = THREE.SRGBColorSpace;
    return t;
}

function woodFloorTex() {
    return canvasTex(512, 512, (ctx, w, h) => {
        const tile = 64;
        const cols = ['#7a5c3a', '#916e42'];
        for (let x = 0; x < w; x += tile) {
            for (let y = 0; y < h; y += tile) {
                ctx.fillStyle = cols[((x / tile) + (y / tile)) & 1];
                ctx.fillRect(x, y, tile, tile);
                ctx.strokeStyle = 'rgba(40,20,0,0.12)';
                ctx.lineWidth = 1;
                for (let i = 0; i < 5; i++) {
                    const ly = y + (i + 0.5) * (tile / 5) + (Math.random() - 0.5) * 3;
                    ctx.beginPath(); ctx.moveTo(x, ly); ctx.lineTo(x + tile, ly + (Math.random() - 0.5) * 2); ctx.stroke();
                }
            }
        }
        ctx.strokeStyle = 'rgba(30,15,0,0.25)';
        ctx.lineWidth = 2;
        for (let x = 0; x <= w; x += tile) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke(); }
        for (let y = 0; y <= h; y += tile) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke(); }
    });
}

/* ═══════════════════════════════════════════════════════════════════
   MATERIAL SHORTCUTS
   ═══════════════════════════════════════════════════════════════════ */

const mat = {
    wood:    (c) => new THREE.MeshStandardMaterial({ color: c, roughness: 0.82, metalness: 0.0 }),
    metal:   (c) => new THREE.MeshStandardMaterial({ color: c, roughness: 0.35, metalness: 0.7 }),
    skin:    (c) => new THREE.MeshStandardMaterial({ color: c, roughness: 0.9,  metalness: 0.0 }),
    fabric:  (c) => new THREE.MeshStandardMaterial({ color: c, roughness: 1.0,  metalness: 0.0 }),
    ceramic: (c) => new THREE.MeshStandardMaterial({ color: c, roughness: 0.4,  metalness: 0.05 }),
    glow:    (c) => new THREE.MeshStandardMaterial({ color: c, emissive: c, emissiveIntensity: 0.8, roughness: 1, metalness: 0 }),
};

/* ═══════════════════════════════════════════════════════════════════
   VOXEL HELPERS
   ═══════════════════════════════════════════════════════════════════ */

function box(w, h, d, material) {
    const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), material);
    m.castShadow = true;
    m.receiveShadow = true;
    return m;
}

function at(mesh, x, y, z) { mesh.position.set(x, y, z); return mesh; }

/* ═══════════════════════════════════════════════════════════════════
   TEXT SPRITE — floating name tags and station signs
   ═══════════════════════════════════════════════════════════════════ */

function textSprite(text, { bg = 'rgba(0,0,0,0.6)', fg = '#ffffff', size = 42, scale = 2.5 } = {}) {
    const c = document.createElement('canvas');
    c.width = 512; c.height = 128;
    const ctx = c.getContext('2d');
    ctx.font = `bold ${size}px "Segoe UI", Arial, sans-serif`;
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    const tw = ctx.measureText(text).width + 30;
    ctx.fillStyle = bg;
    ctx.beginPath();
    ctx.roundRect(256 - tw / 2, 24, tw, 80, 12);
    ctx.fill();
    ctx.fillStyle = fg;
    ctx.fillText(text, 256, 64);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    const s = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false }));
    s.scale.set(scale, scale * 0.25, 1);
    return s;
}

/* ═══════════════════════════════════════════════════════════════════
   STATION STYLES — visual config per category type
   ═══════════════════════════════════════════════════════════════════ */

const STATION_STYLES = {
    coffee:      { icon: '☕', label: 'Coffee',   counter: 0x5c3a1e, counterTop: 0x7a5c3a, charName: 'Barista',      skin: 0xd4a574, shirt: 0xffffff, apron: 0x5c3a1e, pants: 0x2c2c2c, hat: 'beret',   hatClr: 0x5c3a1e, equipment: 'espresso' },
    drink:       { icon: '🧃', label: 'Drinks',   counter: 0x2d6a4f, counterTop: 0x3d8a6a, charName: 'Mixer',        skin: 0xc68642, shirt: 0xf0f0f0, apron: 0x2d6a4f, pants: 0x2c2c2c, hat: 'bandana', hatClr: 0x2d6a4f, equipment: 'blender' },
    dessert:     { icon: '🧁', label: 'Desserts', counter: 0xe8b4b8, counterTop: 0xfff0f5, charName: 'Pastry Chef',  skin: 0xf0c8a0, shirt: 0xfff0f5, apron: 0xff69b4, pants: 0x2c2c2c, hat: 'chef',    hatClr: 0xfff0f5, equipment: 'oven' },
    starter:     { icon: '🍟', label: 'Starters', counter: 0x8b6914, counterTop: 0xb8922a, charName: 'Fry Chef',     skin: 0xe0b090, shirt: 0xffffff, apron: 0xf0f0f0, pants: 0x1a1a2e, hat: 'chef',    hatClr: 0xffffff, equipment: 'fryer' },
    'main dish': { icon: '🍳', label: 'Main Dish',counter: 0x777777, counterTop: 0x888888, charName: 'Head Chef',    skin: 0xd4956a, shirt: 0xffffff, apron: 0xcc4444, pants: 0x1a1a2e, hat: 'chef',    hatClr: 0xffffff, equipment: 'stove' },
};

const FALLBACK_STYLE = { icon: '🍽️', label: 'Kitchen', counter: 0x6b5b3a, counterTop: 0x8a7a5a, charName: 'Chef', skin: 0xd4a574, shirt: 0xffffff, apron: 0xaaaaaa, pants: 0x2c2c2c, hat: 'chef', hatClr: 0xffffff, equipment: 'stove' };

function getStyle(type) {
    return STATION_STYLES[type.toLowerCase().trim()] || FALLBACK_STYLE;
}

/* ═══════════════════════════════════════════════════════════════════
   STATUS LABELS
   ═══════════════════════════════════════════════════════════════════ */

const STATUS_LABELS = {
    placed: 'Placed', created: 'Placed',
    preparing: 'Preparing', brewing: 'Cooking',
    ready: 'Ready', delivered: 'Delivered',
};
const STATUS_SUBS = {
    placed: "Order received — we're on it", created: "Order received — we're on it",
    preparing: 'Kitchen is working on your items', brewing: 'Finishing on the line',
    ready: 'Pick up when you see green', delivered: 'Thank you — enjoy!',
};
const STATUS_MSG_CLASS = {
    placed: 'placed', created: 'placed',
    preparing: 'preparing', brewing: 'brewing',
    ready: 'ready', delivered: 'delivered',
};

/* ═══════════════════════════════════════════════════════════════════
   MAIN APPLICATION CLASS
   ═══════════════════════════════════════════════════════════════════ */

class CafeKitchen {
    constructor() {
        this.characters  = [];
        this.hitBoxes    = [];
        this.steamParts  = [];
        this.clock       = new THREE.Clock();
        this.raycaster   = new THREE.Raycaster();
        this.mouse       = new THREE.Vector2(-10, -10);
        this.hovered     = null;
        this.selected    = null;
        this.autoRotate  = true;
        this.readyCelebrated = false;

        this.orderData = window.KITCHEN_ORDER || null;
        this.trackingMode = !!this.orderData;
        this.demoMode = false; // off by default; use "Demo Mode" button to preview animations
        this.activeStations = new Set();

        this.config = window.KITCHEN_CONFIG || { categories: [], razorpayKeyId: '', appUrl: '', apiBase: '', tableNumber: null };
        this.tableNumber = this.config.tableNumber || null;
        this.categoryTypes = this.config.categories.map(c => c.type);
        this.cartData = { items: [], count: 0, total: 0 };

        if (this.trackingMode) {
            (this.orderData.types || []).forEach(t => {
                this.activeStations.add(t.toLowerCase().trim());
            });
        }

        this.initRenderer();
        this.initScene();
        this.initCamera();
        this.initControls();
        this.initLights();
        this.buildRoom();
        this.buildDynamicStations();
        this.createSteamParticles();
        this.setupInteraction();
        this.buildStatusBar();
        this.setupUI();
        this.initOrdering();
        this.startPolling();
        this.showLoading();

        if (this.trackingMode) {
            this._trackingOrderId = this.orderData.orderId;
            this._pendingPaymentStatus = this.orderData.paymentStatus || 'unpaid';
            this._pendingOrderTotal = this.orderData.totalPrice;
            this.applyOrderStatus(this.orderData.status, this.orderData.items);
            this.renderOrderItems(this.orderData.items);
            if (this._pendingPaymentStatus === 'unpaid') {
                this.showPaymentBanner(this._pendingOrderTotal);
            }
        }

        this.animate();
    }

    /* ── Renderer ─────────────────────────────────────────── */

    initRenderer() {
        this.renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.3;
        document.getElementById('canvas-container').appendChild(this.renderer.domElement);
    }

    /* ── Scene ────────────────────────────────────────────── */

    initScene() {
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x1a0e05);
        this.scene.fog = new THREE.FogExp2(0x1a0e05, 0.012);
    }

    /* ── Camera ───────────────────────────────────────────── */

    initCamera() {
        this.camera = new THREE.PerspectiveCamera(58, window.innerWidth / window.innerHeight, 0.1, 100);
        this.camera.position.set(0, 4.5, 8);
        window.addEventListener('resize', () => {
            this.camera.aspect = window.innerWidth / window.innerHeight;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(window.innerWidth, window.innerHeight);
        });
    }

    /* ── Controls ─────────────────────────────────────────── */

    initControls() {
        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.06;
        this.controls.target.set(0, 1.2, -1);
        this.controls.maxPolarAngle = Math.PI / 2.05;
        this.controls.minPolarAngle = 0.2;
        this.controls.minDistance = 4;
        this.controls.maxDistance = 16;
        this.controls.autoRotate = true;
        this.controls.autoRotateSpeed = 0.25;
    }

    /* ── Lights ───────────────────────────────────────────── */

    initLights() {
        this.scene.add(new THREE.AmbientLight(0xfff5e6, 0.5));

        const dir = new THREE.DirectionalLight(0xffeecc, 0.8);
        dir.position.set(3, 8, 5);
        dir.castShadow = true;
        dir.shadow.mapSize.set(2048, 2048);
        dir.shadow.camera.near = 0.5;
        dir.shadow.camera.far = 25;
        dir.shadow.camera.left = -12;
        dir.shadow.camera.right = 12;
        dir.shadow.camera.top = 12;
        dir.shadow.camera.bottom = -12;
        dir.shadow.bias = -0.0005;
        dir.shadow.normalBias = 0.02;
        this.scene.add(dir);

        this._pendantPositions = [];
    }

    /* ── Room Environment ─────────────────────────────────── */

    buildRoom() {
        const floorTex = woodFloorTex();
        floorTex.repeat.set(5, 5);
        const floor = new THREE.Mesh(
            new THREE.BoxGeometry(30, 0.2, 30),
            new THREE.MeshStandardMaterial({ map: floorTex, roughness: 0.7 })
        );
        floor.position.y = -0.1;
        floor.receiveShadow = true;
        this.scene.add(floor);

        const titleText = this.tableNumber
            ? `NS COFFEE — TABLE ${this.tableNumber}`
            : 'NS COFFEE — LIVE KITCHEN';
        const title = textSprite(titleText, { bg: 'rgba(26,14,5,0.7)', fg: '#e8a87c', size: 36, scale: 4 });
        title.position.set(0, 4.8, -6);
        this.scene.add(title);
    }

    /* ── Dynamic Station Building ─────────────────────────── */

    buildDynamicStations() {
        const types = this.categoryTypes.length > 0 ? this.categoryTypes : Object.keys(STATION_STYLES);
        const N = types.length;
        if (N === 0) return;

        const spread = Math.min(N * 3.5, 16);
        types.forEach((type, i) => {
            const x = N === 1 ? 0 : (i / (N - 1) - 0.5) * spread;
            const z = -3 - Math.abs(i - (N - 1) / 2) * 0.6;
            this.buildStation(type, [x, 0, z]);

            const style = getStyle(type);
            this.buildCharacter({
                name: style.charName,
                station: type,
                pos: [x, 0, z - 1.5],
                skin: style.skin, shirt: style.shirt, apron: style.apron,
                pants: style.pants, hat: style.hat, hatClr: style.hatClr,
                categoryType: type,
            });

            const lightY = 5;
            const warmColors = [0xffaa55, 0xffbb66, 0xff9944, 0xffcc77, 0xffc080];
            const lc = warmColors[i % warmColors.length];
            const pl = new THREE.PointLight(lc, 2.5, 16, 1.6);
            pl.position.set(x, lightY, z - 0.5);
            pl.castShadow = true;
            pl.shadow.mapSize.set(512, 512);
            pl.shadow.bias = -0.002;
            this.scene.add(pl);
            this._pendantPositions.push([x, z - 0.5]);

            this.buildPendantFixture(x, z - 0.5);
        });

        const centerPl = new THREE.PointLight(0xffc080, 2.5, 16, 1.6);
        centerPl.position.set(0, 5, 2);
        centerPl.castShadow = true;
        centerPl.shadow.mapSize.set(512, 512);
        this.scene.add(centerPl);
        this.buildPendantFixture(0, 2);
    }

    buildPendantFixture(x, z) {
        const wire = box(0.04, 0.6, 0.04, mat.metal(0x333333));
        at(wire, x, 5.5, z);
        this.scene.add(wire);
        const shade = box(0.7, 0.25, 0.7, mat.metal(0x2a2a2a));
        at(shade, x, 5.1, z);
        this.scene.add(shade);
        const bulb = box(0.2, 0.12, 0.2, mat.glow(0xffe4b5));
        at(bulb, x, 4.95, z);
        this.scene.add(bulb);
    }

    buildStation(type, pos) {
        const style = getStyle(type);
        const g = new THREE.Group();
        g.position.set(pos[0], pos[1], pos[2]);
        g.userData.stationType = type;

        const counter = box(3, 1, 1.2, mat.wood(style.counter));
        at(counter, 0, 0.5, 0); g.add(counter);
        const top = box(3.1, 0.06, 1.3, mat.wood(style.counterTop));
        at(top, 0, 1.03, 0); g.add(top);

        this._addEquipment(g, style.equipment, style);

        const sign = textSprite(`${style.icon} ${style.label}`, {
            bg: `rgba(${(style.counter >> 16) & 0xff},${(style.counter >> 8) & 0xff},${style.counter & 0xff},0.85)`,
            fg: '#fff', size: 38
        });
        sign.position.set(0, 2.8, -0.5);
        g.add(sign);

        const stationHitBox = new THREE.Mesh(
            new THREE.BoxGeometry(3.2, 2.5, 1.5),
            new THREE.MeshBasicMaterial({ visible: false })
        );
        stationHitBox.position.y = 1.2;
        stationHitBox.userData.stationRef = type;
        g.add(stationHitBox);
        this.hitBoxes.push(stationHitBox);

        this.scene.add(g);
    }

    _addEquipment(g, equipment, style) {
        switch (equipment) {
            case 'espresso': {
                const body = box(0.65, 1.15, 0.5, mat.metal(0x3a3a3a));
                at(body, -0.9, 1.6, -0.2); g.add(body);
                const mTop = box(0.55, 0.2, 0.45, mat.metal(0x4a4a4a));
                at(mTop, -0.9, 2.3, -0.2); g.add(mTop);
                const disp = box(0.08, 0.25, 0.08, mat.metal(0x666666));
                at(disp, -0.9, 1.15, 0.05); g.add(disp);
                const gauge = box(0.12, 0.12, 0.06, mat.glow(0xff6633));
                at(gauge, -0.78, 1.75, 0.01); g.add(gauge);
                for (let i = 0; i < 3; i++) {
                    const cup = box(0.15, 0.18, 0.15, mat.ceramic(0xfaf5ef));
                    at(cup, 0.3 + i * 0.35, 1.15, 0.25); g.add(cup);
                }
                const grinder = box(0.3, 0.55, 0.3, mat.metal(0x555555));
                at(grinder, 0.9, 1.34, -0.2); g.add(grinder);
                const gTop = box(0.22, 0.2, 0.22, mat.metal(0x444444));
                at(gTop, 0.9, 1.72, -0.2); g.add(gTop);
                break;
            }
            case 'blender': {
                const base = box(0.35, 0.15, 0.35, mat.metal(0xdddddd));
                at(base, -0.5, 1.14, 0); g.add(base);
                const jar = box(0.28, 0.55, 0.28, new THREE.MeshStandardMaterial({
                    color: 0xeeeeff, roughness: 0.1, metalness: 0.1, transparent: true, opacity: 0.55
                }));
                at(jar, -0.5, 1.45, 0); g.add(jar);
                const lid = box(0.2, 0.08, 0.2, mat.metal(0xbbbbbb));
                at(lid, -0.5, 1.76, 0); g.add(lid);
                const inside = box(0.22, 0.3, 0.22, mat.glow(0xff8800));
                at(inside, -0.5, 1.35, 0); g.add(inside);
                const fruits = [[0.4, 0xff6600], [0.7, 0xff2222], [0.55, 0x44bb22], [0.3, 0xffcc00]];
                fruits.forEach(([fx, fc]) => {
                    const f = box(0.16, 0.16, 0.16, mat.fabric(fc));
                    at(f, fx, 1.14, -0.1 + Math.random() * 0.3); g.add(f);
                });
                break;
            }
            case 'oven': {
                const body = box(0.9, 0.75, 0.7, mat.metal(0x444444));
                at(body, 0.8, 0.4, 0); g.add(body);
                const door = box(0.7, 0.55, 0.05, new THREE.MeshStandardMaterial({ color: 0x222222, roughness: 0.2, metalness: 0.5 }));
                at(door, 0.8, 0.4, 0.33); g.add(door);
                const oGlow = box(0.5, 0.35, 0.03, mat.glow(0xff4400));
                at(oGlow, 0.8, 0.38, 0.35); g.add(oGlow);
                const handle = box(0.5, 0.04, 0.04, mat.metal(0xaaaaaa));
                at(handle, 0.8, 0.65, 0.38); g.add(handle);
                const bowlO = box(0.45, 0.25, 0.45, mat.ceramic(0xffffff));
                at(bowlO, -0.7, 1.19, 0); g.add(bowlO);
                const cakeBase = box(0.35, 0.2, 0.35, mat.fabric(0xdeb887));
                at(cakeBase, -0.1, 1.16, 0.1); g.add(cakeBase);
                const icing = box(0.37, 0.06, 0.37, mat.fabric(0xff69b4));
                at(icing, -0.1, 1.29, 0.1); g.add(icing);
                break;
            }
            case 'fryer': {
                const fryBody = box(0.9, 0.7, 0.7, mat.metal(0x555555));
                at(fryBody, -0.5, 1.4, 0); g.add(fryBody);
                const oil = box(0.7, 0.15, 0.55, mat.glow(0xcc8800));
                at(oil, -0.5, 1.83, 0); g.add(oil);
                const basket = box(0.55, 0.3, 0.45, new THREE.MeshStandardMaterial({
                    color: 0xcccccc, roughness: 0.4, metalness: 0.6, wireframe: true
                }));
                at(basket, -0.5, 1.7, 0); g.add(basket);
                const bHandle = box(0.04, 0.4, 0.04, mat.metal(0x888888));
                at(bHandle, -0.5, 2.05, 0.3); g.add(bHandle);
                for (let i = 0; i < 3; i++) {
                    const plate = box(0.22, 0.06, 0.22, mat.ceramic(0xfaf5ef));
                    at(plate, 0.4 + i * 0.35, 1.09, 0); g.add(plate);
                }
                break;
            }
            case 'stove':
            default: {
                const stove = box(1.6, 0.1, 0.8, mat.metal(0x333333));
                at(stove, -0.2, 1.11, 0); g.add(stove);
                const burnerMat = mat.glow(0x881100);
                [[-0.55, -0.15], [-0.55, 0.2], [0.15, -0.15], [0.15, 0.2]].forEach(([bx, bz]) => {
                    const b = box(0.28, 0.03, 0.28, burnerMat);
                    at(b, -0.2 + bx, 1.18, bz); g.add(b);
                });
                const pot = box(0.35, 0.35, 0.35, mat.metal(0x555555));
                at(pot, -0.75, 1.34, 0.05); g.add(pot);
                const pan = box(0.4, 0.08, 0.4, mat.metal(0x4a4a4a));
                at(pan, -0.05, 1.2, 0.05); g.add(pan);
                const hood = box(2, 0.2, 1.2, mat.metal(0x555555));
                at(hood, -0.2, 3.5, -0.1); g.add(hood);
                const hoodF = box(2, 0.8, 0.08, mat.metal(0x4a4a4a));
                at(hoodF, -0.2, 3.0, 0.5); g.add(hoodF);
                break;
            }
        }
    }

    /* ── Character Builder ────────────────────────────────── */

    buildCharacter(def) {
        const g = new THREE.Group();
        g.position.set(def.pos[0], def.pos[1], def.pos[2]);
        g.userData = {
            name: def.name, station: def.station, type: 'character',
            status: 'idle', originalY: def.pos[1], baseRotation: 0,
            categoryType: def.categoryType || def.station,
        };

        const skinMat = mat.skin(def.skin);
        const shirtMat = mat.fabric(def.shirt);
        const apronMat = mat.fabric(def.apron);
        const pantsMat = mat.fabric(def.pants);

        const body = box(0.7, 1.0, 0.4, shirtMat);
        at(body, 0, 1.4, 0); g.add(body);

        const apron = box(0.65, 0.75, 0.08, apronMat);
        at(apron, 0, 1.32, 0.2); g.add(apron);

        const head = box(0.65, 0.65, 0.65, skinMat);
        at(head, 0, 2.3, 0); g.add(head);
        g.userData.head = head;

        const eyeMat = new THREE.MeshStandardMaterial({ color: 0x1a1a1a, roughness: 1 });
        const eyeGeo = new THREE.BoxGeometry(0.1, 0.1, 0.04);
        const lEye = new THREE.Mesh(eyeGeo, eyeMat); at(lEye, -0.14, 2.38, 0.33); g.add(lEye);
        const rEye = new THREE.Mesh(eyeGeo, eyeMat); at(rEye, 0.14, 2.38, 0.33); g.add(rEye);

        const mouthMat = new THREE.MeshStandardMaterial({ color: 0x8b4513, roughness: 1 });
        const mouth = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.05, 0.04), mouthMat);
        at(mouth, 0, 2.15, 0.33); g.add(mouth);
        g.userData.mouth = mouth;

        if (def.hat === 'chef') {
            const hatBase = box(0.6, 0.1, 0.6, mat.fabric(def.hatClr));
            at(hatBase, 0, 2.68, 0); g.add(hatBase);
            const hatTop = box(0.5, 0.45, 0.5, mat.fabric(def.hatClr));
            at(hatTop, 0, 2.96, 0); g.add(hatTop);
        } else if (def.hat === 'beret') {
            const beret = box(0.7, 0.15, 0.7, mat.fabric(def.hatClr));
            at(beret, 0.05, 2.7, 0); g.add(beret);
        } else if (def.hat === 'bandana') {
            const band = box(0.68, 0.12, 0.68, mat.fabric(def.hatClr));
            at(band, 0, 2.65, 0); g.add(band);
            const knot = box(0.15, 0.1, 0.15, mat.fabric(def.hatClr));
            at(knot, 0.35, 2.6, -0.2); g.add(knot);
        }

        const armGeo = new THREE.BoxGeometry(0.25, 0.85, 0.25);
        const lArmPivot = new THREE.Group(); lArmPivot.position.set(-0.5, 1.8, 0);
        const lArm = new THREE.Mesh(armGeo, skinMat); lArm.position.y = -0.42; lArm.castShadow = true;
        lArmPivot.add(lArm); g.add(lArmPivot);
        g.userData.leftArm = lArmPivot;

        const rArmPivot = new THREE.Group(); rArmPivot.position.set(0.5, 1.8, 0);
        const rArm = new THREE.Mesh(armGeo, skinMat); rArm.position.y = -0.42; rArm.castShadow = true;
        rArmPivot.add(rArm); g.add(rArmPivot);
        g.userData.rightArm = rArmPivot;

        const legGeo = new THREE.BoxGeometry(0.28, 0.85, 0.28);
        const lLegPivot = new THREE.Group(); lLegPivot.position.set(-0.18, 0.85, 0);
        const lLeg = new THREE.Mesh(legGeo, pantsMat); lLeg.position.y = -0.42; lLeg.castShadow = true;
        lLegPivot.add(lLeg); g.add(lLegPivot);
        g.userData.leftLeg = lLegPivot;

        const rLegPivot = new THREE.Group(); rLegPivot.position.set(0.18, 0.85, 0);
        const rLeg = new THREE.Mesh(legGeo, pantsMat); rLeg.position.y = -0.42; rLeg.castShadow = true;
        rLegPivot.add(rLeg); g.add(rLegPivot);
        g.userData.rightLeg = rLegPivot;

        const nameTag = textSprite(def.name, { bg: 'rgba(0,0,0,0.55)', fg: '#e8a87c', size: 34, scale: 2 });
        nameTag.position.y = 3.6;
        g.add(nameTag);

        const hitBox = new THREE.Mesh(
            new THREE.BoxGeometry(1.2, 3.5, 1.0),
            new THREE.MeshBasicMaterial({ visible: false })
        );
        hitBox.position.y = 1.5;
        hitBox.userData.charRef = g;
        g.add(hitBox);
        this.hitBoxes.push(hitBox);

        this.scene.add(g);
        this.characters.push(g);
        return g;
    }

    /* ── Steam / Particle Effects ─────────────────────────── */

    createSteamParticles() {
        this.characters.forEach(ch => {
            /* Character stands at stationZ − 1.5; counter top is ~y=1.03 at station center.
               Old logic put steam high above and further back (z−2), so it sat *behind* the pass.
               Anchor steam on the counter surface, slightly toward the camera (+z). */
            const stationZ = ch.position.z + 1.5;
            const base = new THREE.Vector3(
                ch.position.x + (Math.random() - 0.5) * 0.35,
                1.12,
                stationZ + 0.38
            );
            for (let i = 0; i < 6; i++) {
                const spriteMat = new THREE.SpriteMaterial({
                    color: 0xffffff,
                    transparent: true,
                    opacity: 0,
                    blending: THREE.AdditiveBlending,
                    depthWrite: false,
                    depthTest: false,
                });
                const sprite = new THREE.Sprite(spriteMat);
                sprite.renderOrder = 999;
                sprite.scale.setScalar(0.18 + Math.random() * 0.12);
                sprite.position.copy(base);
                sprite.position.x += (Math.random() - 0.5) * 0.25;
                sprite.position.z += (Math.random() - 0.5) * 0.2;
                const p0 = sprite.position.clone();
                sprite.userData = {
                    base: p0,
                    speed: 0.35 + Math.random() * 0.45,
                    drift: (Math.random() - 0.5) * 0.12,
                    lifetime: 2.5 + Math.random() * 1.5,
                    age: Math.random() * 3,
                    maxOpacity: 0.28 + Math.random() * 0.14,
                };
                this.scene.add(sprite);
                this.steamParts.push(sprite);
            }
        });
    }

    updateParticles(delta) {
        this.steamParts.forEach(p => {
            const d = p.userData;
            d.age += delta;
            if (d.age > d.lifetime) {
                d.age = 0;
                p.position.copy(d.base);
                p.position.x += (Math.random() - 0.5) * 0.35;
                p.position.z += (Math.random() - 0.5) * 0.22;
            }
            const t = d.age / d.lifetime;
            p.position.y += d.speed * delta;
            p.position.x += d.drift * delta;
            p.material.opacity = d.maxOpacity * Math.sin(t * Math.PI);
            const s = 0.15 + t * 0.2;
            p.scale.setScalar(s);
        });
    }

    /* ── Character Animations ─────────────────────────────── */

    updateAnimations(time) {
        this.characters.forEach(ch => {
            const u = ch.userData;
            const { leftArm: la, rightArm: ra, leftLeg: ll, rightLeg: rl, head } = u;
            const s = u.status;

            switch (s) {
                case 'placed':
                    la.rotation.x = Math.sin(time * 2) * 0.25;
                    ra.rotation.x = Math.sin(time * 2 + Math.PI) * 0.25;
                    la.rotation.z = 0; ra.rotation.z = 0;
                    ll.rotation.x = 0; rl.rotation.x = 0;
                    ch.position.y = u.originalY + Math.sin(time * 3) * 0.025;
                    head.rotation.y = Math.sin(time * 2.5) * 0.25;
                    head.rotation.x = Math.sin(time * 1.5) * 0.08;
                    ch.rotation.y = u.baseRotation;
                    break;

                case 'preparing':
                    la.rotation.x = Math.sin(time * 4.5) * 0.55;
                    ra.rotation.x = Math.sin(time * 4.5 + Math.PI) * 0.55;
                    la.rotation.z = -0.12; ra.rotation.z = 0.12;
                    ll.rotation.x = Math.sin(time * 2) * 0.06;
                    rl.rotation.x = Math.sin(time * 2 + Math.PI) * 0.06;
                    ch.position.y = u.originalY + Math.sin(time * 2.5) * 0.03;
                    head.rotation.y = Math.sin(time * 1.8) * 0.15;
                    head.rotation.x = -0.1;
                    ch.rotation.y = u.baseRotation + Math.sin(time * 1.5) * 0.05;
                    break;

                case 'cooking':
                    la.rotation.x = Math.sin(time * 7) * 0.85;
                    ra.rotation.x = Math.sin(time * 7 + Math.PI) * 0.85;
                    la.rotation.z = -0.15; ra.rotation.z = 0.15;
                    ll.rotation.x = Math.sin(time * 3.5) * 0.15;
                    rl.rotation.x = Math.sin(time * 3.5 + Math.PI) * 0.15;
                    ch.position.y = u.originalY + Math.abs(Math.sin(time * 5)) * 0.07;
                    head.rotation.y = Math.sin(time * 3) * 0.22;
                    head.rotation.x = -0.15;
                    ch.rotation.y = u.baseRotation + Math.sin(time * 2.5) * 0.12;
                    break;

                case 'ready':
                    la.rotation.x = -2.6 + Math.sin(time * 7) * 0.3;
                    ra.rotation.x = -2.6 + Math.sin(time * 7 + 1) * 0.3;
                    la.rotation.z = -0.45 + Math.sin(time * 5) * 0.15;
                    ra.rotation.z = 0.45 - Math.sin(time * 5) * 0.15;
                    ll.rotation.x = Math.sin(time * 6) * 0.08;
                    rl.rotation.x = Math.sin(time * 6 + Math.PI) * 0.08;
                    ch.position.y = u.originalY + Math.abs(Math.sin(time * 4.5)) * 0.4;
                    head.rotation.y = Math.sin(time * 5) * 0.35;
                    head.rotation.x = 0;
                    head.rotation.z = Math.sin(time * 3.5) * 0.12;
                    ch.rotation.y = u.baseRotation;
                    break;

                default:
                    la.rotation.x = Math.sin(time * 1) * 0.08;
                    ra.rotation.x = Math.sin(time * 1 + Math.PI) * 0.08;
                    la.rotation.z = 0; ra.rotation.z = 0;
                    ll.rotation.x = 0; rl.rotation.x = 0;
                    ch.position.y = u.originalY + Math.sin(time * 1.5) * 0.035;
                    head.rotation.y = Math.sin(time * 0.7) * 0.1;
                    head.rotation.x = 0; head.rotation.z = 0;
                    ch.rotation.y = u.baseRotation;
                    break;
            }
        });
    }

    /* ── Demo Mode ────────────────────────────────────────── */

    updateDemoMode(time) {
        if (!this.demoMode) return;
        const states = ['idle', 'placed', 'preparing', 'cooking', 'ready'];
        this.characters.forEach((ch, i) => {
            const offset = i * 4;
            const cycle = (time + offset) % 22;
            let idx;
            if      (cycle < 4)  idx = 0;
            else if (cycle < 8)  idx = 1;
            else if (cycle < 12) idx = 2;
            else if (cycle < 17) idx = 3;
            else                 idx = 4;
            ch.userData.status = states[idx];
        });
    }

    /* ── Interaction ──────────────────────────────────────── */

    setupInteraction() {
        const canvas = this.renderer.domElement;

        canvas.addEventListener('mousemove', (e) => {
            this.mouse.x = (e.clientX / window.innerWidth) * 2 - 1;
            this.mouse.y = -(e.clientY / window.innerHeight) * 2 + 1;
        });

        canvas.addEventListener('click', () => {
            if (this.hovered) {
                const charRef = this.hovered.userData?.charRef || this.hovered;
                const stationRef = this.hovered.userData?.stationRef;
                if (stationRef) {
                    this.showProductPanel(stationRef);
                } else if (charRef?.userData?.type === 'character') {
                    this.selected = charRef;
                    this.showCharInfo(charRef);
                }
            } else {
                this.selected = null;
                this.hideCharInfo();
            }
        });

        canvas.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                this.mouse.x = (e.touches[0].clientX / window.innerWidth) * 2 - 1;
                this.mouse.y = -(e.touches[0].clientY / window.innerHeight) * 2 + 1;
            }
        });
    }

    checkHover() {
        this.raycaster.setFromCamera(this.mouse, this.camera);
        const hits = this.raycaster.intersectObjects(this.hitBoxes);

        if (this.hovered) {
            if (this.hovered.userData?.charRef) {
                this.hovered.userData.charRef.scale.lerp(new THREE.Vector3(1, 1, 1), 0.15);
            }
            this.hovered = null;
            document.body.style.cursor = 'default';
        }

        if (hits.length > 0) {
            const obj = hits[0].object;
            if (obj.userData.charRef) {
                obj.userData.charRef.scale.lerp(new THREE.Vector3(1.06, 1.06, 1.06), 0.3);
                this.hovered = obj;
                document.body.style.cursor = 'pointer';
            } else if (obj.userData.stationRef) {
                this.hovered = obj;
                document.body.style.cursor = 'pointer';
            }
        }
    }

    showCharInfo(charGroup) {
        const el = document.getElementById('char-info');
        const name = charGroup.userData.name;
        const status = charGroup.userData.status;
        const style = getStyle(charGroup.userData.categoryType || charGroup.userData.station);
        const statusMessages = {
            idle: `Waiting for ${style.label.toLowerCase()} orders...`,
            placed: 'New order received!',
            preparing: `Preparing your ${style.label.toLowerCase()}...`,
            cooking: `Making the perfect ${style.label.toLowerCase()}!`,
            ready: `Your ${style.label.toLowerCase()} is ready! 🎉`,
        };
        const msg = statusMessages[status] || 'Working...';

        el.querySelector('.info-name').textContent = name;
        el.querySelector('.info-msg').textContent = msg;
        const badge = el.querySelector('.info-badge');
        badge.textContent = status.toUpperCase();
        badge.className = 'info-badge ' + status;

        const menuBtn = document.getElementById('info-view-menu-btn');
        if (menuBtn && !this.trackingMode) {
            menuBtn.style.display = 'inline-block';
            menuBtn.dataset.category = charGroup.userData.categoryType || charGroup.userData.station;
        } else if (menuBtn) {
            menuBtn.style.display = 'none';
        }

        el.classList.add('visible');
    }

    hideCharInfo() {
        document.getElementById('char-info').classList.remove('visible');
    }

    updateInfoPosition() {
        if (!this.selected) return;
        const el = document.getElementById('char-info');
        if (!el.classList.contains('visible')) return;

        const style = getStyle(this.selected.userData.categoryType || this.selected.userData.station);
        const status = this.selected.userData.status;
        const statusMessages = {
            idle: `Waiting for ${style.label.toLowerCase()} orders...`,
            placed: 'New order received!',
            preparing: `Preparing your ${style.label.toLowerCase()}...`,
            cooking: `Making the perfect ${style.label.toLowerCase()}!`,
            ready: `Your ${style.label.toLowerCase()} is ready! 🎉`,
        };
        el.querySelector('.info-msg').textContent = statusMessages[status] || 'Working...';
        const badge = el.querySelector('.info-badge');
        badge.textContent = status.toUpperCase();
        badge.className = 'info-badge ' + status;

        const v = new THREE.Vector3(0, 3.8, 0);
        this.selected.localToWorld(v);
        v.project(this.camera);
        const x = (v.x * 0.5 + 0.5) * window.innerWidth;
        const y = (-v.y * 0.5 + 0.5) * window.innerHeight;
        el.style.left = `${x - el.offsetWidth / 2}px`;
        el.style.top = `${y - el.offsetHeight - 15}px`;
    }

    /* ═══════════════════════════════════════════════════════════
       ORDERING SYSTEM
       ═══════════════════════════════════════════════════════════ */

    initOrdering() {
        if (this.trackingMode) {
            document.getElementById('cart-fab').style.display = 'none';
            const orderMoreBtn = document.getElementById('btn-order-more');
            if (orderMoreBtn) {
                orderMoreBtn.addEventListener('click', () => {
                    this.exitTrackingMode();
                });
            }
        } else {
            document.getElementById('cart-fab').style.display = 'flex';
        }

        document.getElementById('cart-fab')?.addEventListener('click', () => this.showCart());

        document.getElementById('pp-close')?.addEventListener('click', () => this.hideProductPanel());
        document.getElementById('cd-close')?.addEventListener('click', () => this.hideCart());
        document.getElementById('panel-scrim')?.addEventListener('click', () => {
            this.hideProductPanel();
            this.hideCart();
        });

        const menuBtn = document.getElementById('info-view-menu-btn');
        if (menuBtn) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const cat = menuBtn.dataset.category;
                this.hideCharInfo();
                if (cat) this.showProductPanel(cat);
            });
        }

        document.getElementById('ck-cancel')?.addEventListener('click', () => this.hideCheckout());
        document.getElementById('ck-pay')?.addEventListener('click', () => this.submitCheckout());

        document.getElementById('pb-pay-btn')?.addEventListener('click', () => this.payNow());

        document.getElementById('ib-dismiss')?.addEventListener('click', () => {
            document.getElementById('instruction-banner')?.classList.add('hidden');
        });

        if (this.trackingMode) {
            document.getElementById('instruction-banner')?.classList.add('hidden');
        }

        this.loadCart();
    }

    exitTrackingMode() {
        this.trackingMode = false;
        this.demoMode = false;
        this.orderData = null;
        this._trackingOrderId = null;
        this._pendingPaymentStatus = null;
        this._pendingOrderTotal = null;
        this.activeStations.clear();
        clearInterval(this._pollTimer);
        this.readyCelebrated = false;

        const baseUrl = window.location.pathname + (this.tableNumber ? `?table=${this.tableNumber}` : '');
        history.pushState({}, '', baseUrl);
        document.getElementById('order-track')?.classList.remove('visible');
        const sm = document.getElementById('status-msg');
        if (sm) {
            sm.classList.remove('visible', 'status-msg--placed', 'status-msg--preparing', 'status-msg--brewing', 'status-msg--ready', 'status-msg--delivered');
        }
        this.hidePaymentBanner();
        const orderItems = document.getElementById('order-items');
        if (orderItems) orderItems.innerHTML = '';

        document.getElementById('cart-fab').style.display = 'flex';

        const ctrlDiv = document.getElementById('controls');
        if (ctrlDiv) {
            ctrlDiv.innerHTML = `
                <button class="ctrl-btn" id="btn-demo" title="Toggle demo animation cycle">Demo Mode</button>
                <button class="ctrl-btn" id="btn-rotate" title="Toggle auto-rotation">Auto Rotate</button>
            `;
            this._rebindCtrlButtons();
        }

        const titleEl = document.querySelector('.scene-title');
        if (titleEl) {
            titleEl.innerHTML = this.tableNumber
                ? `<span>🔥</span> Table ${this.tableNumber} Kitchen`
                : '<span>🔥</span> Live Kitchen';
        }

        this.characters.forEach(ch => { ch.userData.status = 'idle'; });
        this.loadCart();
        this.fetchStatus();
        setInterval(() => this.fetchStatus(), 3000);
    }

    _rebindCtrlButtons() {
        const demoBtn = document.getElementById('btn-demo');
        demoBtn?.addEventListener('click', () => {
            this.demoMode = !this.demoMode;
            demoBtn.classList.toggle('active', this.demoMode);
            if (!this.demoMode) {
                this.characters.forEach(ch => { ch.userData.status = 'idle'; });
            }
        });
        const rotBtn = document.getElementById('btn-rotate');
        rotBtn?.addEventListener('click', () => {
            this.autoRotate = !this.autoRotate;
            this.controls.autoRotate = this.autoRotate;
            rotBtn.classList.toggle('active', this.autoRotate);
        });
    }

    /* ── Product Panel ── */

    showProductPanel(categoryType) {
        const cat = this.config.categories.find(c => c.type.toLowerCase() === categoryType.toLowerCase());
        if (!cat || !cat.products.length) return;

        const panel = document.getElementById('product-panel');
        const title = document.getElementById('pp-title');
        const body = document.getElementById('pp-body');
        const style = getStyle(categoryType);

        title.textContent = `${style.icon} ${style.label}`;
        body.innerHTML = '';

        cat.products.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `
                <img class="product-img" src="${this.config.appUrl}/images/${p.image}" alt="${p.name}" onerror="this.style.display='none'">
                <div class="product-info">
                    <h4>${p.name}</h4>
                    <div class="pdesc">${p.description || ''}</div>
                    <div class="pprice">₹${p.price}</div>
                    <div class="product-actions">
                        <select class="size-select" data-pid="${p.id}">
                            <option value="Small">S</option>
                            <option value="Medium" selected>M</option>
                            <option value="Large">L</option>
                        </select>
                        <div class="qty-ctrl">
                            <button class="qty-minus" data-pid="${p.id}">−</button>
                            <span class="qty-val" id="qty-${p.id}">1</span>
                            <button class="qty-plus" data-pid="${p.id}">+</button>
                        </div>
                        <button class="add-btn" data-pid="${p.id}">Add</button>
                    </div>
                </div>
            `;
            body.appendChild(card);
        });

        body.querySelectorAll('.qty-minus').forEach(btn => {
            btn.addEventListener('click', () => {
                const el = document.getElementById('qty-' + btn.dataset.pid);
                const v = Math.max(1, parseInt(el.textContent) - 1);
                el.textContent = v;
            });
        });
        body.querySelectorAll('.qty-plus').forEach(btn => {
            btn.addEventListener('click', () => {
                const el = document.getElementById('qty-' + btn.dataset.pid);
                const v = Math.min(20, parseInt(el.textContent) + 1);
                el.textContent = v;
            });
        });
        body.querySelectorAll('.add-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const pid = btn.dataset.pid;
                const size = body.querySelector(`.size-select[data-pid="${pid}"]`).value;
                const qty = parseInt(document.getElementById('qty-' + pid).textContent);
                btn.disabled = true;
                btn.textContent = '...';
                this.addToCart(pid, size, qty).then(() => {
                    btn.textContent = '✓ Added';
                    setTimeout(() => { btn.disabled = false; btn.textContent = 'Add'; }, 1200);
                });
            });
        });

        panel.classList.add('open');
        document.getElementById('panel-scrim').classList.add('visible');
    }

    hideProductPanel() {
        document.getElementById('product-panel').classList.remove('open');
        if (!document.getElementById('cart-drawer').classList.contains('open')) {
            document.getElementById('panel-scrim').classList.remove('visible');
        }
    }

    /* ── Cart AJAX ── */

    async addToCart(productId, size, quantity) {
        try {
            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('product_id', productId);
            fd.append('size', size);
            fd.append('quantity', quantity);
            const res = await fetch(this.config.apiBase + '/cart.php', { method: 'POST', body: fd });
            this.cartData = await res.json();
            this.updateCartBadge();
        } catch { /* silent */ }
    }

    async loadCart() {
        try {
            const res = await fetch(this.config.apiBase + '/cart.php');
            this.cartData = await res.json();
            this.updateCartBadge();
        } catch { /* silent */ }
    }

    updateCartBadge() {
        const badge = document.getElementById('cart-badge');
        if (!badge) return;
        const count = this.cartData.count || 0;
        badge.textContent = count;
        badge.classList.toggle('visible', count > 0);
    }

    showCart() {
        this.loadCart().then(() => this.renderCartDrawer());
        document.getElementById('cart-drawer').classList.add('open');
        document.getElementById('panel-scrim').classList.add('visible');
    }

    hideCart() {
        document.getElementById('cart-drawer').classList.remove('open');
        if (!document.getElementById('product-panel').classList.contains('open')) {
            document.getElementById('panel-scrim').classList.remove('visible');
        }
    }

    renderCartDrawer() {
        const body = document.getElementById('cd-body');
        const footer = document.getElementById('cd-footer');

        if (!this.cartData.items || this.cartData.items.length === 0) {
            body.innerHTML = '<div class="cart-empty">Your cart is empty.<br>Click a station to start ordering!</div>';
            footer.innerHTML = '';
            return;
        }

        body.innerHTML = '';
        this.cartData.items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'cart-item';
            div.innerHTML = `
                <img class="cart-item-img" src="${this.config.appUrl}/images/${item.image}" alt="${item.name}" onerror="this.style.display='none'">
                <div class="cart-item-info">
                    <h4>${item.name}</h4>
                    <div class="cart-meta">${item.size} · Qty: ${item.quantity}</div>
                </div>
                <div class="cart-item-right">
                    <div class="cart-item-price">₹${item.line_total}</div>
                    <button class="cart-remove" data-cid="${item.id}">Remove</button>
                </div>
            `;
            body.appendChild(div);
        });

        body.querySelectorAll('.cart-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                this.removeCartItem(btn.dataset.cid);
            });
        });

        footer.innerHTML = `
            <div class="cart-total">Total: <span>₹${this.cartData.total}</span></div>
            <button class="k3d-btn-primary" id="btn-checkout">Proceed to Checkout</button>
        `;
        document.getElementById('btn-checkout')?.addEventListener('click', () => {
            this.hideCart();
            this.showCheckout();
        });
    }

    async removeCartItem(cartId) {
        try {
            const fd = new FormData();
            fd.append('action', 'remove');
            fd.append('cart_id', cartId);
            const res = await fetch(this.config.apiBase + '/cart.php', { method: 'POST', body: fd });
            this.cartData = await res.json();
            this.updateCartBadge();
            this.renderCartDrawer();
        } catch { /* silent */ }
    }

    /* ── Checkout ── */

    showCheckout() {
        const overlay = document.getElementById('checkout-overlay');
        const summary = document.getElementById('ck-summary');
        const err = document.getElementById('ck-error');
        err.style.display = 'none';

        let html = '';
        (this.cartData.items || []).forEach(item => {
            html += `<div class="cs-row"><span>${item.name} × ${item.quantity}</span><span>₹${item.line_total}</span></div>`;
        });
        html += `<div class="cs-total"><span>Total</span><span>₹${this.cartData.total}</span></div>`;
        summary.innerHTML = html;

        overlay.classList.add('open');
    }

    hideCheckout() {
        document.getElementById('checkout-overlay').classList.remove('open');
    }

    async submitCheckout() {
        const fname = document.getElementById('ck-fname').value.trim();
        const lname = document.getElementById('ck-lname').value.trim();
        const phone = document.getElementById('ck-phone').value.trim();
        const email = document.getElementById('ck-email').value.trim();
        const err = document.getElementById('ck-error');

        if (!fname || !phone || !email) {
            err.textContent = 'Please fill in all required fields.';
            err.style.display = 'block';
            return;
        }

        const payBtn = document.getElementById('ck-pay');
        payBtn.disabled = true;
        payBtn.textContent = 'Placing order...';
        err.style.display = 'none';

        try {
            const fd = new FormData();
            fd.append('first_name', fname);
            fd.append('last_name', lname);
            fd.append('phone', phone);
            fd.append('email', email);
            const res = await fetch(this.config.apiBase + '/checkout.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.error) {
                err.textContent = data.error;
                err.style.display = 'block';
                payBtn.disabled = false;
                payBtn.textContent = 'Place Order';
                return;
            }

            this.hideCheckout();
            this._pendingOrderTotal = data.total;
            this._pendingPaymentStatus = 'unpaid';
            this.switchToTrackingMode(data.order_id);
        } catch (e) {
            err.textContent = 'Network error — please try again.';
            err.style.display = 'block';
            payBtn.disabled = false;
            payBtn.textContent = 'Place Order';
        }
    }

    /* ── Pay Now (deferred payment) ── */

    async payNow() {
        const btn = document.getElementById('pb-pay-btn');
        if (!btn || !this._trackingOrderId) return;
        btn.disabled = true;
        btn.textContent = 'Loading...';

        try {
            const fd = new FormData();
            fd.append('order_id', this._trackingOrderId);
            const res = await fetch(this.config.apiBase + '/pay.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.error) {
                alert(data.error);
                btn.disabled = false;
                btn.textContent = 'Pay Now';
                return;
            }

            this.openRazorpay(data);
        } catch {
            alert('Network error — please try again.');
            btn.disabled = false;
            btn.textContent = 'Pay Now';
        }
    }

    openRazorpay(details) {
        const options = {
            key: details.key_id,
            amount: details.amount,
            currency: details.currency,
            name: 'NS Coffee',
            description: 'Kitchen Order',
            order_id: details.razorpay_order_id,
            handler: (response) => {
                this.onPaymentSuccess(details.order_id, response);
            },
            modal: {
                ondismiss: () => {
                    const btn = document.getElementById('pb-pay-btn');
                    if (btn) { btn.disabled = false; btn.textContent = 'Pay Now'; }
                }
            },
            theme: { color: '#c49b63' }
        };
        const rzp = new Razorpay(options);
        rzp.open();
    }

    async onPaymentSuccess(orderId, response) {
        try {
            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('razorpay_payment_id', response.razorpay_payment_id);
            fd.append('razorpay_order_id', response.razorpay_order_id);
            fd.append('razorpay_signature', response.razorpay_signature);
            const res = await fetch(this.config.apiBase + '/verify-payment.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                this._pendingPaymentStatus = 'paid';
                this.hidePaymentBanner();
            } else {
                alert('Payment verification failed: ' + (data.error || 'Unknown error'));
                const btn = document.getElementById('pb-pay-btn');
                if (btn) { btn.disabled = false; btn.textContent = 'Pay Now'; }
            }
        } catch {
            alert('Network error during verification — check your order status.');
            const btn = document.getElementById('pb-pay-btn');
            if (btn) { btn.disabled = false; btn.textContent = 'Pay Now'; }
        }
    }

    /* ── Payment Banner ── */

    showPaymentBanner(total) {
        const banner = document.getElementById('payment-banner');
        if (!banner) return;
        document.getElementById('pb-amount').textContent = `₹${total}`;
        banner.style.display = 'flex';
    }

    hidePaymentBanner() {
        const banner = document.getElementById('payment-banner');
        if (banner) banner.style.display = 'none';
    }

    /* ── Switch to Tracking Mode ── */

    async switchToTrackingMode(orderId) {
        this.demoMode = false;
        this.trackingMode = true;
        this.readyCelebrated = false;
        this._trackingOrderId = orderId;
        this.cartData = { items: [], count: 0, total: 0 };
        this.updateCartBadge();

        const trackUrl = this.tableNumber
            ? `${window.location.pathname}?table=${this.tableNumber}&order_id=${orderId}`
            : `${window.location.pathname}?order_id=${orderId}`;
        history.pushState({}, '', trackUrl);

        document.getElementById('cart-fab').style.display = 'none';
        document.getElementById('instruction-banner')?.classList.add('hidden');
        this.hideProductPanel();
        this.hideCart();
        this.hideCheckout();

        if (this._pendingPaymentStatus === 'unpaid') {
            this.showPaymentBanner(this._pendingOrderTotal || '?');
        }

        const titleEl = document.querySelector('.scene-title');
        const tableSuffix = this.tableNumber ? ` · Table ${this.tableNumber}` : '';
        if (titleEl) titleEl.innerHTML = `<span>🔥</span> Order #${orderId}${tableSuffix}`;

        const ctrlDiv = document.getElementById('controls');
        if (ctrlDiv) {
            const invoiceUrl = `${this.config.appUrl}/payments/invoice.php?order_id=${orderId}`;
            ctrlDiv.innerHTML = `
                <a href="${invoiceUrl}" class="ctrl-btn">📄 Invoice</a>
                <button class="ctrl-btn" id="btn-order-more" style="background:rgba(196,155,99,0.2);border-color:#c49b63;">Order more</button>
                <button class="ctrl-btn" id="btn-rotate" title="Toggle auto-rotation">Auto Rotate</button>
            `;
            document.getElementById('btn-order-more')?.addEventListener('click', () => this.exitTrackingMode());
            const rotBtn = document.getElementById('btn-rotate');
            rotBtn?.addEventListener('click', () => {
                this.autoRotate = !this.autoRotate;
                this.controls.autoRotate = this.autoRotate;
                rotBtn.classList.toggle('active', this.autoRotate);
            });
        }

        try {
            const statusUrl = `${this.config.appUrl}/qr-order/order-status.php?order_id=${orderId}`;
            const res = await fetch(statusUrl);
            const data = await res.json();

            this.orderData = {
                orderId,
                status: data.status || 'preparing',
                items: data.items || [],
                types: data.item_types || [],
                apiUrl: statusUrl,
            };

            this.activeStations.clear();
            (this.orderData.types || []).forEach(t => {
                this.activeStations.add(t.toLowerCase().trim());
            });

            this.applyOrderStatus(this.orderData.status, this.orderData.items || []);
            this.renderOrderItems(this.orderData.items || []);
        } catch {
            this.orderData = { orderId, status: 'preparing', items: [], types: [], apiUrl: `${this.config.appUrl}/qr-order/order-status.php?order_id=${orderId}` };
            this.applyOrderStatus('preparing', []);
        }

        document.getElementById('order-track')?.classList.add('visible');
        clearInterval(this._pollTimer);
        this._pollTimer = setInterval(() => this.pollOrder(), 3000);
    }

    /* ── Backend Status Polling ────────────────────────────── */

    startPolling() {
        if (this.trackingMode) {
            this.pollOrder();
            this._pollTimer = setInterval(() => this.pollOrder(), 3000);
        } else {
            this.fetchStatus();
            setInterval(() => this.fetchStatus(), 3000);
        }
    }

    async pollOrder() {
        if (!this.orderData) return;
        try {
            const res = await fetch(this.orderData.apiUrl);
            if (!res.ok) return;
            const data = await res.json();
            if (data.error) return;
            if (data.item_types) {
                this.activeStations.clear();
                data.item_types.forEach(t => {
                    this.activeStations.add(t.toLowerCase().trim());
                });
            }
            if (data.items) {
                this.orderData.items = data.items;
                this.renderOrderItems(data.items);
            }
            if (data.status) {
                this.applyOrderStatus(data.status, data.items || this.orderData.items);
            }
            if (data.payment_status === 'paid' && this._pendingPaymentStatus !== 'paid') {
                this._pendingPaymentStatus = 'paid';
                this.hidePaymentBanner();
            }
            if (data.status === 'ready' || data.status === 'delivered') {
                clearInterval(this._pollTimer);
            }
        } catch { /* silent */ }
    }

    /**
     * @param {string} dbStatus — aggregate order status (progress bar / headline)
     * @param {Array<{type?:string, item_status?:string}>|null} items — per-line kitchen status (station animations)
     */
    applyOrderStatus(dbStatus, items) {
        const animMap = {
            created: 'idle', placed: 'placed',
            preparing: 'preparing', brewing: 'cooking',
            ready: 'ready', delivered: 'ready',
        };
        const linePri = { created: 1, placed: 1, preparing: 2, brewing: 3, ready: 4, delivered: 5, cancelled: 0 };
        const lineToAnim = {
            created: 'idle', placed: 'placed',
            preparing: 'preparing', brewing: 'cooking',
            ready: 'ready', delivered: 'idle', cancelled: 'idle',
        };

        const itemList = items && items.length ? items : null;

        if (itemList) {
            this.characters.forEach(ch => {
                const catType = (ch.userData.categoryType || ch.userData.station || '').toLowerCase().trim();
                if (!this.activeStations.has(catType)) {
                    ch.userData.status = 'idle';
                    return;
                }
                const relevant = itemList.filter(i =>
                    (i.type || 'coffee').toLowerCase().trim() === catType
                );
                if (!relevant.length) {
                    ch.userData.status = 'idle';
                    return;
                }
                const active = relevant.filter(i => {
                    const s = (i.item_status || 'placed').toLowerCase();
                    return s !== 'delivered';
                });
                if (!active.length) {
                    ch.userData.status = 'idle';
                    return;
                }
                let minP = Infinity;
                let worst = 'placed';
                active.forEach(i => {
                    const s = (i.item_status || 'placed').toLowerCase();
                    const p = linePri[s] ?? 1;
                    if (p < minP) {
                        minP = p;
                        worst = s;
                    }
                });
                ch.userData.status = lineToAnim[worst] || 'idle';
            });
        } else {
            const animStatus = animMap[dbStatus] || 'idle';
            this.characters.forEach(ch => {
                const catType = ch.userData.categoryType || ch.userData.station;
                if (this.activeStations.has(catType)) {
                    ch.userData.status = animStatus;
                } else {
                    ch.userData.status = 'idle';
                }
            });
        }

        this.updateProgressBar(dbStatus);
        this.updateStatusMessage(dbStatus);
        this.syncStatusBar();

        if ((dbStatus === 'ready' || dbStatus === 'delivered') && !this.readyCelebrated) {
            this.readyCelebrated = true;
            this.launchConfetti();
            setTimeout(() => this.launchConfetti(), 1500);
        }
    }

    updateProgressBar(dbStatus) {
        const steps = document.querySelectorAll('#order-track .track-step');
        if (!steps.length) return;
        const idx = { placed: 0, created: 0, preparing: 1, brewing: 2, ready: 3, delivered: 3 }[dbStatus] ?? 0;
        steps.forEach((el, i) => {
            el.classList.remove('active', 'done');
            if (i < idx) el.classList.add('done');
            else if (i === idx) el.classList.add('active');
        });
        for (let i = 1; i <= 3; i++) {
            const fill = document.getElementById('fill-' + i);
            if (fill) fill.style.width = (i <= idx ? '100%' : '0%');
        }
    }

    updateStatusMessage(dbStatus) {
        const el = document.getElementById('status-msg');
        if (!el) return;
        const phase = STATUS_MSG_CLASS[dbStatus] || 'placed';
        el.classList.remove(
            'status-msg--placed', 'status-msg--preparing', 'status-msg--brewing', 'status-msg--ready', 'status-msg--delivered'
        );
        el.classList.add('status-msg--' + phase);
        el.classList.add('visible');
        const txt = el.querySelector('.status-text');
        const sub = el.querySelector('.status-sub');
        if (txt) txt.textContent = STATUS_LABELS[dbStatus] || dbStatus;
        if (sub) sub.textContent = STATUS_SUBS[dbStatus] || '';
    }

    renderOrderItems(items) {
        const wrap = document.getElementById('order-items');
        if (!wrap || !items) return;
        const dotColors = {
            coffee: '#6b4226', drink: '#1a8a1a', dessert: '#f0a0b8',
            starter: '#c49b63', 'main dish': '#c49b63',
        };
        wrap.innerHTML = '';
        const stLabel = {
            placed: 'Placed', preparing: 'Preparing', brewing: 'Cooking', ready: 'Ready', delivered: 'Served', cancelled: '—',
        };
        items.forEach(it => {
            const t = (it.type || 'coffee').toLowerCase().trim();
            const col = dotColors[t] || '#c49b63';
            const chip = document.createElement('div');
            chip.className = 'order-chip';
            const is = (it.item_status || 'placed').toLowerCase();
            const lab = stLabel[is] || is;
            chip.innerHTML = `<span class="chip-dot" style="background:${col}"></span>${it.name} × ${it.quantity} <span class="chip-status">· ${lab}</span>`;
            wrap.appendChild(chip);
        });
    }

    launchConfetti() {
        const canvas = document.getElementById('confetti-canvas');
        if (!canvas) return;
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        const ctx = canvas.getContext('2d');
        const colors = ['#c49b63', '#FFD700', '#ff6060', '#60ff60', '#6060ff', '#f0a0b8', '#fff'];
        const pieces = [];
        for (let i = 0; i < 80; i++) {
            pieces.push({
                x: Math.random() * canvas.width,
                y: -20 - Math.random() * 100,
                w: 4 + Math.random() * 8,
                h: 6 + Math.random() * 10,
                color: colors[Math.floor(Math.random() * colors.length)],
                vy: 2 + Math.random() * 3,
                vx: (Math.random() - 0.5) * 3,
                rot: Math.random() * 360,
                vr: (Math.random() - 0.5) * 12,
                life: 1,
            });
        }
        const draw = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let alive = false;
            pieces.forEach(p => {
                if (p.life <= 0) return;
                alive = true;
                p.x += p.vx; p.y += p.vy; p.rot += p.vr;
                p.vy += 0.05;
                if (p.y > canvas.height * 0.7) p.life -= 0.02;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot * Math.PI / 180);
                ctx.globalAlpha = Math.max(0, p.life);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });
            if (alive) requestAnimationFrame(draw);
            else ctx.clearRect(0, 0, canvas.width, canvas.height);
        };
        requestAnimationFrame(draw);
    }

    async fetchStatus() {
        try {
            const tableQ = this.tableNumber ? `?table=${this.tableNumber}` : '';
            const res = await fetch('getOrderStatus.php' + tableQ);
            if (!res.ok) return;
            const data = await res.json();
            if (data.stations && !this.demoMode) {
                this.characters.forEach(ch => {
                    const catType = ch.userData.categoryType || ch.userData.station;
                    const st = data.stations[catType];
                    if (st && st.status && st.status !== 'idle') {
                        ch.userData.status = st.status;
                    } else if (!this.demoMode) {
                        ch.userData.status = 'idle';
                    }
                });
            }
            this.syncStatusBar();
        } catch { /* silent */ }
    }

    syncStatusBar() {
        this.characters.forEach(ch => {
            const id = ch.userData.categoryType || ch.userData.station;
            const dot = document.getElementById('dot-' + id);
            const txt = document.getElementById('stxt-' + id);
            if (dot) { dot.className = 'station-dot ' + ch.userData.status; }
            if (txt) { txt.textContent = ch.userData.status; }
        });
    }

    /* ── Build Status Bar (dynamic) ── */

    buildStatusBar() {
        const bar = document.getElementById('status-bar');
        if (!bar) return;
        bar.innerHTML = '';
        const types = this.categoryTypes.length > 0 ? this.categoryTypes : Object.keys(STATION_STYLES);
        types.forEach(type => {
            const style = getStyle(type);
            const pill = document.createElement('div');
            pill.className = 'station-pill';
            pill.dataset.station = type;
            pill.innerHTML = `
                <div class="station-dot idle" id="dot-${type}"></div>
                <div>
                    <div class="station-label">${style.icon} ${style.label}</div>
                    <div class="station-status-text" id="stxt-${type}">idle</div>
                </div>
            `;
            bar.appendChild(pill);
        });
    }

    /* ── UI Controls ──────────────────────────────────────── */

    setupUI() {
        this._rebindCtrlButtons();

        document.querySelectorAll('.station-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const stationId = pill.dataset.station;
                const ch = this.characters.find(c => (c.userData.categoryType || c.userData.station) === stationId);
                if (ch) {
                    this.selected = ch;
                    this.showCharInfo(ch);
                    const target = ch.position.clone();
                    target.y += 2;
                    this.controls.target.copy(target);
                }
            });
        });
    }

    /* ── Loading Screen ───────────────────────────────────── */

    showLoading() {
        const fill = document.getElementById('load-fill');
        let progress = 0;
        const interval = setInterval(() => {
            progress += 8 + Math.random() * 15;
            if (progress >= 100) {
                progress = 100;
                fill.style.width = '100%';
                clearInterval(interval);
                setTimeout(() => {
                    document.getElementById('loading').classList.add('done');
                    if (this.trackingMode) {
                        setTimeout(() => {
                            const msg = document.getElementById('status-msg');
                            if (msg) msg.classList.add('visible');
                        }, 500);
                    }
                }, 400);
            } else {
                fill.style.width = progress + '%';
            }
        }, 150);
    }

    /* ── Render Loop ──────────────────────────────────────── */

    animate() {
        requestAnimationFrame(() => this.animate());

        const delta = this.clock.getDelta();
        const time  = this.clock.getElapsedTime();

        this.controls.update();
        this.updateDemoMode(time);
        this.updateAnimations(time);
        this.updateParticles(delta);
        this.checkHover();
        this.updateInfoPosition();

        if (this.demoMode) {
            this.syncStatusBar();
        }

        this.renderer.render(this.scene, this.camera);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   BOOT
   ═══════════════════════════════════════════════════════════════════ */

window.addEventListener('DOMContentLoaded', () => {
    new CafeKitchen();
});
