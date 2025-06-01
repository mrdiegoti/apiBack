<?php

namespace App\Http\Controllers;

use App\Models\Peticione;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeticioneController extends Controller
{
    public function index()
    {
        return response()->json(Peticione::all(), 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|max:255',
            'descripcion' => 'required',
            'destinatario' => 'required',
            'categoria_id' => 'required|exists:categorias,id',
            'imagen*' => 'nullable|image|max:2048'
        ]);

        $data = $request->all();


        $imagenes = [];

        if ($request->hasFile('imagen')) {
            foreach ($request->file('imagen') as $file) {
                $imagenes[] = $file->store('peticiones', 'public');
            }
        }

        $data['imagen'] = json_encode($imagenes);

        $peticion = new Peticione($data);
        $peticion->user_id = Auth::id();
        $peticion->firmantes = 0;
        $peticion->estado = 'pendiente';
        $peticion->save();

        return response()->json($peticion, 201);
    }


    public function show($id)
    {
        $peticione = Peticione::findOrFail($id);
        return response()->json($peticione, 200);
    }

    public function update(Request $request, $id)
    {
        $peticione = Peticione::findOrFail($id);

        // Extrae únicamente los campos que deseas actualizar
        $data = $request->only(['titulo', 'descripcion', 'destinatario', 'categoria_id']);

        // Si se ha enviado una nueva imagen, la procesa y actualiza la ruta
        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('peticiones', 'public');
            $data['imagen'] = $path;
        }

        $peticione->update($data);

        return response()->json($peticione, 200);
    }


    public function listmine(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
        $misPeticiones = $user->peticiones()->get();
        return response()->json($misPeticiones, 200);
    }



    public function delete($id)
    {
        $peticione = Peticione::findOrFail($id);
        $this->authorize('delete', $peticione);

        $peticione->delete();
        return response()->json(['message' => 'Petición eliminada'], 200);
    }

    public function firmar($id)
{
    $user = Auth::user();
    if (! $user) {
        return response()->json(['message' => 'No autenticado'], 401);
    }

    $peticion = Peticione::findOrFail($id);

    // Verificar si ya ha firmado
    if ($peticion->firmas()->where('user_id', $user->id)->exists()) {
        return response()->json(['message' => 'Ya has firmado esta petición'], 400);
    }

    // Guardar firma en la tabla pivote
    $peticion->firmas()->attach($user->id);

    // Opcional: actualizar contador
    $peticion->firmantes = $peticion->firmantes + 1;
    $peticion->save();

    return response()->json(['message' => 'Petición firmada con éxito']);
}




    public function cambiarEstado($id, Request $request)
{
    $user = Auth::user();

    // ✅ Solo permitir si es administrador (role_id = 1)
    if (! $user || $user->role_id !== 1) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    $peticion = Peticione::findOrFail($id);

    $request->validate([
        'estado' => 'required|in:pendiente,aceptada,rechazada'
    ]);

    $peticion->estado = $request->estado;
    $peticion->save();

    return response()->json(['message' => 'Estado actualizado correctamente']);
}


    public function firmadas()
{
    $user = Auth::user();

    if (! $user) {
        return response()->json(['message' => 'No autenticado'], 401);
    }

    // Obtener las peticiones que ha firmado el usuario
    $peticionesFirmadas = $user->firmas()->with('categoria', 'user')->get();

    return response()->json($peticionesFirmadas);
}
}
