/* Real geometry, lighting and basting; Three.js r180 is hosted locally. */
import * as THREE from './vendor/three/three.module.min.js';

export function createGrill(container) {
  const renderer = new THREE.WebGLRenderer({alpha:true,antialias:true,powerPreference:'low-power'});
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
  renderer.setClearColor(0x000000,0);
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.35;
  const canvas = renderer.domElement;
  canvas.className = 'grill-canvas';
  canvas.tabIndex = 0;
  canvas.setAttribute('role','img');
  canvas.setAttribute('aria-label','3D barbecue grill. Drag horizontally or use left and right arrow keys to rotate.');
  container.appendChild(canvas);
  const scene = new THREE.Scene();
  const camera = new THREE.OrthographicCamera(-2.65,2.65,2.65,-2.65,.1,35);
  camera.position.set(4.8,4.8,6.5);
  camera.lookAt(0,.55,.2);
  const root = new THREE.Group();scene.add(root);
  scene.add(new THREE.HemisphereLight(0xfff1dc,0x47433a,2));
  const key = new THREE.DirectionalLight(0xffe1b1,4.2);
  key.position.set(-3,7,5);key.castShadow=true;
  key.shadow.mapSize.set(512,512);
  Object.assign(key.shadow.camera,{left:-4,right:4,top:4,bottom:-4,near:.1,far:16});
  key.shadow.normalBias=.035;scene.add(key);
  const rim=new THREE.DirectionalLight(0xdbe9ff,3);rim.position.set(4,3,-4);scene.add(rim);
  const warm=new THREE.PointLight(0xff5b13,2,5);warm.position.set(0,.4,0);root.add(warm);
  const material=(color,roughness=.55,metalness=0)=>new THREE.MeshStandardMaterial({color,roughness,metalness});
  const iron=material(0x282a29,.75,.55),steel=material(0x9ca4a5,.25,.9);
  const bamboo=material(0xe2b66f,.5),wood=material(0xad7139,.4);
  const char=material(0x25170f,.95),green=material(0x568126,.6);
  function mesh(geometry,mat,x,y,z,parent=root) {
    const item=new THREE.Mesh(geometry,mat);item.position.set(x,y,z);
    item.castShadow=true;item.receiveShadow=true;parent.add(item);return item;
  }
  function box(w,h,d,x,y,z,mat=iron,parent=root) {
    return mesh(new THREE.BoxGeometry(w,h,d),mat,x,y,z,parent);
  }
  function rod(radius,length,x,y,z,mat,axis='y',parent=root) {
    const item=mesh(new THREE.CylinderGeometry(radius,radius,length,10),mat,x,y,z,parent);
    if(axis==='x')item.rotation.z=Math.PI/2;
    if(axis==='z')item.rotation.x=Math.PI/2;
    return item;
  }
  // Open steel body: the charcoal and grates are separate physical surfaces.
  box(3.35,.14,2.7,0,-.14,0);
  box(3.35,.62,.12,0,.22,1.31);
  box(3.35,.62,.12,0,.22,-1.31);
  box(.12,.62,2.7,-1.62,.22,0);box(.12,.62,2.7,1.62,.22,0);
  for(const x of [-1.36,1.36])for(const z of [-1.05,1.05])box(.3,.35,.3,x,-.36,z);
  for(const x of [-1.7,1.7]) {
    rod(.045,2.7,x,.58,0,steel,'z');
    rod(.04,.42,x*1.11,.25,0,iron,'z');
    for(const z of [-.21,.21])rod(.04,.22,x*1.055,.25,z,iron,'x');
  }
  for(const z of [-1.35,1.35])rod(.045,3.4,0,.58,z,steel,'x');
  for(let z=-1.16;z<1.3;z+=.2)rod(.022,3.25,0,.56,z,steel,'x');
  rod(.025,2.7,-1.1,.53,0,steel,'z');rod(.025,2.7,1.1,.53,0,steel,'z');
  const emberMaterials=[];
  for(let x=-1.3;x<=1.4;x+=.5)for(let z=-1;z<=1.1;z+=.48) {
    const m=new THREE.MeshStandardMaterial({color:0x231914,roughness:1,emissive:0xe83c05,emissiveIntensity:.25});
    emberMaterials.push(m);
    const coal=mesh(new THREE.DodecahedronGeometry(.24,0),m,x,.16,z);
    coal.scale.set(1,.55,1);coal.rotation.set(x*2,z*3,x+z);
  }
  for(const x of [-1,0,1]) {
    const hole=mesh(new THREE.CircleGeometry(.105,16),char,x,.19,1.375);
    hole.scale.y=.6;
    const glow=new THREE.MeshBasicMaterial({color:0xc74c0e});
    const inside=mesh(new THREE.CircleGeometry(.055,12),glow,x,.18,1.38);inside.scale.y=.55;
  }
  // A shared procedural food texture adds char patches and meat fibers.
  let seed=7241;
  function random(){seed=(seed*16807)%2147483647;return(seed-1)/2147483646;}
  const foodCanvas=document.createElement('canvas');foodCanvas.width=foodCanvas.height=256;
  const paint=foodCanvas.getContext('2d');paint.fillStyle='#ad421d';paint.fillRect(0,0,256,256);
  for(let i=0;i<1100;i++){
    const v=random();paint.fillStyle=v>.78?'rgba(38,20,12,.8)':v>.45?'rgba(239,145,57,.23)':'rgba(82,25,12,.28)';
    paint.beginPath();paint.ellipse(random()*256,random()*256,2+random()*9,1+random()*4,random()*3,0,Math.PI*2);paint.fill();
  }
  const foodTexture=new THREE.CanvasTexture(foodCanvas);foodTexture.colorSpace=THREE.SRGBColorSpace;
  const foodMaterials=[],skewers=[];
  for(let index=0;index<4;index++){
    const group=new THREE.Group();group.position.x=-1.05+index*.7;root.add(group);skewers.push(group);
    rod(.026,3.65,0,.79,.28,bamboo,'z',group);
    const mat=new THREE.MeshPhysicalMaterial({color:index===3?0xe5a34b:0xc67b42,map:foodTexture,roughness:.5,clearcoat:.4,clearcoatRoughness:.45,specularIntensity:.35});
    foodMaterials.push(mat);
    if(index===3){
      const points=[];
      for(let p=0;p<=150;p++){const t=p/150;points.push(new THREE.Vector3(Math.sin(t*Math.PI*14)*.22,.85+Math.cos(t*Math.PI*14)*.04,-1.02+t*2.08));}
      mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(points),150,.075,7,false),mat,0,0,0,group);
    }else{
      for(let piece=0;piece<4;piece++){
        const geometry=new THREE.SphereGeometry(.25,18,12);
        const positions=geometry.attributes.position;
        for(let p=0;p<positions.count;p++){
          const x=positions.getX(p),y=positions.getY(p),z=positions.getZ(p);
          const bump=1+.12*Math.sin(x*31+piece)*Math.cos(z*27+index);
          const foldedX=Math.sign(x)*Math.pow(Math.abs(x)/.25,.62)*.25;
          const foldedZ=Math.sign(z)*Math.pow(Math.abs(z)/.25,.62)*.25;
          positions.setXYZ(p,foldedX*bump,y*(.57+Math.cos(z*13)*.1)+Math.sin(x*17+piece)*.025,foldedZ*bump);
        }
        geometry.computeVertexNormals();
        const item=mesh(geometry,mat,(random()-.5)*.06,.84,-.82+piece*.55,group);
        item.scale.set(1,.95,1.12);item.rotation.y=(random()-.5)*.45;
        // Narrow char marks follow the curved upper surface, rather than floating in front.
        for(let mark=0;mark<2;mark++){
          const stripe=new THREE.Mesh(new THREE.TorusGeometry(.205,.012,5,16,Math.PI*.85),char);
          stripe.position.set(0,.842,-.91+piece*.55+mark*.14);
          stripe.rotation.z=.25;stripe.scale.y=.69;group.add(stripe);
        }
      }
    }
  }
  // Calamansi on a small metal side ledge, modeled with rind and cut segments.
  box(.64,.07,.9,1.98,.55,-.8,steel);
  mesh(new THREE.SphereGeometry(.205,20,12),green,2,.79,-1.03);
  mesh(new THREE.SphereGeometry(.235,24,12,0,Math.PI*2,Math.PI/2,Math.PI/2),green,2.03,.85,-.5);
  const pulp=mesh(new THREE.CircleGeometry(.212,32),material(0xe1d56f,.5),2.03,.852,-.5);pulp.rotation.x=-Math.PI/2;
  const segmentMat=new THREE.LineBasicMaterial({color:0xffefb2});
  for(let i=0;i<9;i++){
    const a=i*Math.PI*2/9;
    const geo=new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(2.03,.856,-.5),new THREE.Vector3(2.03+Math.cos(a)*.204,.856,-.5+Math.sin(a)*.204)]);
    root.add(new THREE.Line(geo,segmentMat));
  }
  // The brush is a mesh in the same world: wood, metal ferrule, individual bristles.
  const brush=new THREE.Group();root.add(brush);brush.visible=false;
  rod(.065,.84,0,.89,0,wood,'y',brush);
  mesh(new THREE.SphereGeometry(.065,10,6),wood,0,1.31,0,brush);
  box(.44,.16,.18,0,.39,0,steel,brush);
  const bristleMat=material(0x9b3515,.47);
  for(let x=-.19;x<.21;x+=.038)for(const z of [-.055,0,.055])box(.027,.27,.03,x,.175,z,bristleMat,brush);
  const smokeCanvas=document.createElement('canvas');smokeCanvas.width=smokeCanvas.height=64;
  const smokePaint=smokeCanvas.getContext('2d');
  const gradient=smokePaint.createRadialGradient(32,32,0,32,32,32);
  gradient.addColorStop(0,'rgba(231,230,218,.55)');gradient.addColorStop(.5,'rgba(231,230,218,.15)');gradient.addColorStop(1,'rgba(231,230,218,0)');
  smokePaint.fillStyle=gradient;smokePaint.fillRect(0,0,64,64);
  const smokeTexture=new THREE.CanvasTexture(smokeCanvas),smoke=[];
  for(let i=0;i<14;i++){
    const sprite=new THREE.Sprite(new THREE.SpriteMaterial({map:smokeTexture,transparent:true,depthWrite:false,opacity:.15}));
    root.add(sprite);smoke.push(sprite);
  }
  const targetYaw={value:0};let yaw=0,playing=false,time=0,basting=-1,frame=0,last=0,visible=true,disposed=false,drag=null;
  function render(){renderer.render(scene,camera);}
  function setSize(){
    const w=Math.max(container.clientWidth,1),h=Math.max(container.clientHeight,1),aspect=w/h;
    camera.left=-2.65*aspect;camera.right=2.65*aspect;camera.updateProjectionMatrix();renderer.setSize(w,h,false);render();
  }
  const resize=new ResizeObserver(setSize);resize.observe(container);
  function tick(now){
    frame=0;if(disposed)return;
    const dt=last?Math.min((now-last)/1000,.15):0;last=now;
    if(playing&&visible&&!document.hidden){
      time+=dt;
      yaw+=(targetYaw.value-yaw)*.13;
      root.rotation.y=yaw+Math.sin(time*.28)*.055;
      emberMaterials.forEach((m,i)=>m.emissiveIntensity=.3+.3*(.5+.5*Math.sin(time*2+i*.7)));
      smoke.forEach((sprite,i)=>{
        const life=(time*.22+i/14)%1;
        sprite.position.set(Math.sin(i*7+life*3)*(.25+life*.25),1.02+life*1.65,Math.cos(i*3)*.7);
        const size=.18+life*.65;sprite.scale.set(size,size,size);sprite.material.opacity=Math.sin(life*Math.PI)*.3;
      });
      if(basting>=0){
        basting+=dt;
        const progress=Math.min(basting/3.2,.999),stroke=Math.floor(progress*4),t=progress*4-stroke;
        brush.visible=true;brush.position.set(-1.05+stroke*.7,1.01+Math.pow(Math.abs(t-.5)*2,3)*.52,-1.1+t*2.25);
        brush.rotation.set(.12,0,-.35+Math.sin(t*Math.PI)*.12);
        foodMaterials[stroke].clearcoat=.8;foodMaterials[stroke].roughness=.38;
        if(basting>=3.2)finishBaste();
      }
      render();
    }
    if(playing&&visible&&!document.hidden)frame=requestAnimationFrame(tick);
  }
  function wake(){if(!frame&&!disposed){last=0;frame=requestAnimationFrame(tick);}}
  function finishBaste(){basting=-1;brush.visible=false;foodMaterials.forEach(m=>{m.clearcoat=.8;m.roughness=.38;});render();}
  function setPlaying(value){playing=value;if(!value){cancelAnimationFrame(frame);frame=0;last=0;render();}else wake();}
  const intersection=new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;if(visible&&playing)wake();});intersection.observe(container);
  function visibility(){if(!document.hidden&&playing)wake();}document.addEventListener('visibilitychange',visibility);
  canvas.addEventListener('pointerdown',event=>{if(event.button!==0)return;drag={x:event.clientX,y:event.clientY,start:targetYaw.value};});
  canvas.addEventListener('pointermove',event=>{
    if(!drag)return;const dx=event.clientX-drag.x,dy=event.clientY-drag.y;
    if(Math.abs(dx)<5||Math.abs(dy)>Math.abs(dx)*1.2)return;
    canvas.setPointerCapture(event.pointerId);targetYaw.value=drag.start+dx*.012;yaw=targetYaw.value;root.rotation.y=yaw;render();
  });
  function release(){drag=null;}canvas.addEventListener('pointerup',release);canvas.addEventListener('pointercancel',release);
  canvas.addEventListener('keydown',event=>{
    if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;
    event.preventDefault();targetYaw.value+=event.key==='ArrowLeft'?-.18:.18;yaw=targetYaw.value;root.rotation.y=yaw;render();
  });
  setSize();
  return {
    setPlaying,
    baste(){if(!playing){finishBaste();return;}basting=0;brush.visible=true;wake();},
    finishBaste,
    dispose(){disposed=true;cancelAnimationFrame(frame);resize.disconnect();intersection.disconnect();document.removeEventListener('visibilitychange',visibility);scene.traverse(o=>{o.geometry?.dispose();if(o.material){const ms=Array.isArray(o.material)?o.material:[o.material];ms.forEach(m=>m.dispose());}});foodTexture.dispose();smokeTexture.dispose();renderer.dispose();canvas.remove();},
  };
}
