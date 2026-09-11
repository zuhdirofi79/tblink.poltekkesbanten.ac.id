import{r as s,c as r,j as e}from"./app-CUJzyYBJ.js";import{R as o}from"./refresh-ccw-cep4uztF.js";import{c as d}from"./createLucideIcon-DYGsZJPT.js";/**
 * @license lucide-react v0.546.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const m=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M12 16v-4",key:"1dtifu"}],["path",{d:"M12 8h.01",key:"e9boi3"}]],l=d("info",m);function u(){const[a,i]=s.useState(),[n,t]=s.useState(!1);return s.useEffect(()=>{t(!0),r.get(route("ds-data.pendamping.index")).then(({data:c})=>i(c)).finally(()=>t(!1))},[]),e.jsx("div",{className:"my-3",children:n?e.jsx("div",{className:"flex justify-center items-center",children:e.jsx(o,{className:"animate-spin",size:40})}):a?e.jsxs("p",{className:"text-lg font-semibold text-primary flex gap-2 items-center",children:[e.jsx(l,{size:20}),e.jsxs("div",{children:["Anda login sebagai Pendamping Pasien a/n ",e.jsx("span",{className:"text-destructive",children:a.nama})]})]}):""})}export{u as default};
